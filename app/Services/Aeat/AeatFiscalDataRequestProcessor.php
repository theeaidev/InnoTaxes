<?php

namespace App\Services\Aeat;

use App\Models\AeatFiscalDataFile;
use App\Models\AeatFiscalDataRecord;
use App\Models\AeatFiscalDataRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class AeatFiscalDataRequestProcessor
{
    /**
     * Create a new processor instance.
     */
    public function __construct(
        protected AeatHttpClient $httpClient,
        protected AeatFiscalDataParser $parser,
    ) {
    }

    /**
     * Process a queued AEAT request.
     */
    public function process(int $requestId, int $attempt, int $maxAttempts): void
    {
        /** @var AeatFiscalDataRequest $request */
        $request = AeatFiscalDataRequest::query()
            ->with(['certificateProfile', 'precheckCertificateProfile', 'files'])
            ->findOrFail($requestId);

        if ($request->status === 'completed') {
            return;
        }

        $request->forceFill([
            'status' => 'processing',
            'stage' => 'processing',
            'processing_at' => Carbon::now(),
            'attempts' => $attempt,
        ])->save();

        $this->safeLog('info', 'Processing AEAT fiscal-data request.', [
            'request_id' => $request->getKey(),
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'auth_method' => $request->auth_method,
            'taxpayer_nif' => $request->taxpayer_nif,
        ]);

        $ratificationProfile = $this->resolveRatificationProfile($request);
        if ($ratificationProfile) {
            $request->forceFill(['stage' => 'ratification_check'])->save();
            $ratification = $this->httpClient->checkRatification($ratificationProfile, $request->taxpayer_nif);
            $request->forceFill([
                'domicile_status' => $ratification['domicile_ratified'] ? 'ratified' : 'not_ratified',
                'last_checked_domicile_at' => Carbon::now(),
            ])->save();

            if (! $ratification['domicile_ratified']) {
                throw new AeatIntegrationException(
                    message: 'Contribuyente no ha ratificado su domicilio fiscal.',
                    stage: 'ratification_check',
                    errorCode: '5001',
                    context: ['response' => $ratification['response']],
                    retryable: false,
                );
            }
        }

        $this->guardFiscalDataAvailability();

        $body = match ($request->auth_method) {
            'certificate' => $this->downloadWithCertificate($request),
            'reference' => $this->downloadWithReference($request),
            'clave_movil' => $this->downloadWithClaveMovil($request),
            default => throw new AeatIntegrationException(
                message: 'Unsupported AEAT authentication method.',
                stage: 'request_dispatch',
                retryable: false,
            ),
        };

        $request->forceFill([
            'stage' => 'parsing',
            'downloaded_at' => Carbon::now(),
        ])->save();

        $parsed = $this->parser->parse($body);
        $file = $this->storeRawFile($request, $body, $parsed['summary']);
        $this->storeRecords($request, $file, $parsed['records']);

        $request->forceFill([
            'status' => 'completed',
            'stage' => 'completed',
            'completed_at' => Carbon::now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'last_error_context' => null,
            'session_state' => $this->sanitizeSessionStateForSuccess($request->session_state ?? []),
        ])->save();

        $this->safeLog('info', 'AEAT fiscal-data request completed.', [
            'request_id' => $request->getKey(),
            'records' => $parsed['summary']['total_records'],
            'auth_method' => $request->auth_method,
        ]);
    }

    /**
     * Persist a failure for the current request.
     */
    public function recordFailure(int $requestId, Throwable $throwable, int $attempt, int $maxAttempts, bool $willRetry, ?string $forcedStatus = null): void
    {
        /** @var AeatFiscalDataRequest|null $request */
        $request = AeatFiscalDataRequest::query()->find($requestId);
        if (! $request) {
            return;
        }

        $stage = $throwable instanceof AeatIntegrationException ? $throwable->stage() : 'unexpected';
        $errorCode = $throwable instanceof AeatIntegrationException ? $throwable->errorCode() : null;
        $context = $throwable instanceof AeatIntegrationException ? $throwable->context() : ['exception' => $throwable::class];
        $sanitizedContext = $this->sanitizeForJson($context);
        $retryable = $throwable instanceof AeatIntegrationException ? $throwable->retryable() : true;
        $status = $forcedStatus ?? ($willRetry ? 'retrying' : 'failed');
        $domicileStatus = ($errorCode === '5001' || str_contains(strtolower($throwable->getMessage()), 'ratificado'))
            ? 'not_ratified'
            : $request->domicile_status;
        $safeMessage = $this->sanitizeUtf8($throwable->getMessage());

        $request->forceFill([
            'status' => $status,
            'stage' => $stage,
            'attempts' => $attempt,
            'last_error_code' => $errorCode,
            'last_error_message' => $safeMessage,
            'last_error_context' => $sanitizedContext,
            'domicile_status' => $domicileStatus,
            'completed_at' => $willRetry ? null : Carbon::now(),
        ])->save();

        $request->errors()->create([
            'stage' => $stage,
            'code' => $errorCode,
            'message' => $safeMessage,
            'details' => $sanitizedContext,
            'retryable' => $retryable,
            'attempt' => $attempt,
            'occurred_at' => Carbon::now(),
        ]);

        $this->safeLog('warning', 'AEAT fiscal-data request failed.', [
            'request_id' => $request->getKey(),
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'stage' => $stage,
            'error_code' => $errorCode,
            'message' => $safeMessage,
            'retryable' => $retryable,
            'will_retry' => $willRetry,
        ]);
    }

    /**
     * Download data with client-certificate authentication.
     */
    protected function downloadWithCertificate(AeatFiscalDataRequest $request): string
    {
        if (! $request->certificateProfile) {
            throw new AeatIntegrationException(
                message: 'A certificate profile is required for this request.',
                stage: 'certificate_download',
                retryable: false,
            );
        }

        $request->certificateProfile->forceFill(['last_used_at' => Carbon::now()])->save();

        return $this->httpClient->downloadWithCertificate($request->certificateProfile, $request->taxpayer_nif, $request->pdp);
    }

    /**
     * Download data using the AEAT reference flow.
     */
    protected function downloadWithReference(AeatFiscalDataRequest $request): string
    {
        $referenceHash = data_get($request->session_state, 'reference_hash');
        if (! is_string($referenceHash) || $referenceHash === '') {
            throw new AeatIntegrationException(
                message: 'The encrypted reference hash is no longer available for this request.',
                stage: 'reference_auth',
                retryable: false,
            );
        }

        $cookieJar = $this->httpClient->authenticateReference(
            $request->auth_nif ?: $request->taxpayer_nif,
            $referenceHash,
        );

        return $this->httpClient->downloadWithCookies(
            (string) config('aeat.urls.reference_download'),
            $cookieJar,
            $request->taxpayer_nif,
            $request->pdp,
        );
    }

    /**
     * Download data using the Cl@ve Movil flow.
     */
    protected function downloadWithClaveMovil(AeatFiscalDataRequest $request): string
    {
        $pin = data_get($request->session_state, 'pin');
        if (! is_string($pin) || $pin === '') {
            throw new AeatIntegrationException(
                message: 'The Cl@ve Movil PIN is required before downloading fiscal data.',
                stage: 'clave_validate_pin',
                retryable: false,
            );
        }

        $cookieJar = $this->httpClient->validateClaveMovilPin($request->session_state ?? [], $pin);

        return $this->httpClient->downloadWithCookies(
            (string) config('aeat.urls.clave_movil.download'),
            $cookieJar,
            $request->taxpayer_nif,
            $request->pdp,
        );
    }

    /**
     * Stop before download when AEAT has not opened the selected exercise yet.
     */
    protected function guardFiscalDataAvailability(): void
    {
        $releaseDate = config('aeat.release_date');
        if (! is_string($releaseDate) || $releaseDate === '') {
            return;
        }

        $releaseAt = Carbon::parse($releaseDate)->startOfDay();
        if (Carbon::now()->lt($releaseAt)) {
            throw new AeatIntegrationException(
                message: sprintf(
                    'AEAT todavia no permite descargar los datos fiscales del ejercicio %s. Segun el calendario oficial, el acceso comienza el %s.',
                    (string) config('aeat.exercise'),
                    $releaseAt->format('d/m/Y'),
                ),
                stage: 'service_unavailable',
                context: [
                    'exercise' => (string) config('aeat.exercise'),
                    'release_date' => $releaseAt->toDateString(),
                ],
                retryable: false,
            );
        }
    }

    /**
     * Select the certificate profile used for the domicile ratification check.
     */
    protected function resolveRatificationProfile(AeatFiscalDataRequest $request)
    {
        if ($request->precheckCertificateProfile) {
            return $request->precheckCertificateProfile;
        }

        if ($request->auth_method === 'certificate') {
            return $request->certificateProfile;
        }

        return null;
    }

    /**
     * Store the raw AEAT file on disk.
     *
     * @param  array<string, mixed>  $summary
     */
    protected function storeRawFile(AeatFiscalDataRequest $request, string $body, array $summary): AeatFiscalDataFile
    {
        $this->clearExistingArtifacts($request);

        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = 'aeat_fiscal_data_'.$request->getKey().'_'.$timestamp.'.txt';
        $path = 'private/aeat/files/'.$request->user_id.'/'.$filename;
        Storage::disk('local')->put($path, $body);

        return $request->files()->create([
            'disk' => 'local',
            'path' => $path,
            'filename' => $filename,
            'sha256' => hash('sha256', $body),
            'bytes' => strlen($body),
            'line_count' => (int) count(array_filter(preg_split('/\r\n|\n|\r/', trim($body)))),
            'record_count' => (int) ($summary['total_records'] ?? 0),
            'meta' => [
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Store normalized AEAT records in bulk.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function storeRecords(AeatFiscalDataRequest $request, AeatFiscalDataFile $file, array $records): void
    {
        $timestamp = Carbon::now();
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                'aeat_fiscal_data_request_id' => $request->getKey(),
                'aeat_fiscal_data_file_id' => $file->getKey(),
                'line_number' => $record['line_number'],
                'record_type' => $record['record_type'],
                'record_code' => $record['record_code'],
                'layout_key' => $record['layout_key'],
                'line_length' => $record['line_length'],
                'raw_line' => $record['raw_line'],
                'normalized_data' => json_encode($record['normalized_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'parse_warnings' => json_encode($record['warnings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('aeat_fiscal_data_records')->insert($chunk);
        }
    }

    /**
     * Remove previous generated files and records when a request is retried.
     */
    protected function clearExistingArtifacts(AeatFiscalDataRequest $request): void
    {
        foreach ($request->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        AeatFiscalDataRecord::query()->where('aeat_fiscal_data_request_id', $request->getKey())->delete();
        AeatFiscalDataFile::query()->where('aeat_fiscal_data_request_id', $request->getKey())->delete();
    }

    /**
     * Drop no-longer-needed secrets after a successful request.
     *
     * @param  array<string, mixed>  $sessionState
     * @return array<string, mixed>
     */
    protected function sanitizeSessionStateForSuccess(array $sessionState): array
    {
        unset($sessionState['pin'], $sessionState['reference_hash'], $sessionState['cookies'], $sessionState['token_clave_movil_sms'], $sessionState['timestamp_alta_sms']);

        return $sessionState;
    }

    /**
     * Log without letting logging errors change request state.
     *
     * @param  array<string, mixed>  $context
     */
    protected function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('aeat')->log($level, $message, $this->sanitizeForJson($context));
        } catch (Throwable) {
            // Logging should never break queue processing.
        }
    }

    /**
     * Sanitize mixed data so it can be safely encoded as JSON.
     */
    protected function sanitizeForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$this->sanitizeUtf8((string) $key)] = $this->sanitizeForJson($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeUtf8($value);
        }

        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $this->sanitizeForJson($value->jsonSerialize());
            }

            if ($value instanceof \Stringable) {
                return $this->sanitizeUtf8((string) $value);
            }

            return $this->sanitizeForJson((array) $value);
        }

        return $value;
    }

    /**
     * Normalize strings before persisting them to JSON columns.
     */
    protected function sanitizeUtf8(string $value): string
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);

            return $value;
        } catch (JsonException) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');

            if (is_string($converted) && $converted !== '') {
                return $converted;
            }

            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if (is_string($clean) && $clean !== '') {
                return $clean;
            }

            return '[binary data removed]';
        }
    }
}
