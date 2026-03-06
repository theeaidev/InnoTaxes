<?php

namespace App\Services\Aeat;

use App\Jobs\ProcessAeatFiscalDataRequest;
use App\Models\AeatFiscalDataRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AeatFiscalDataManager
{
    /**
     * Create a new manager instance.
     */
    public function __construct(
        protected AeatCertificateVault $certificateVault,
        protected AeatHttpClient $httpClient,
        protected AeatFiscalDataRequestProcessor $processor,
    ) {
    }

    /**
     * Store a new certificate profile for the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeCertificateProfile(User $user, array $data)
    {
        /** @var UploadedFile $certificateFile */
        $certificateFile = $data['certificate_file'];
        /** @var UploadedFile|null $privateKeyFile */
        $privateKeyFile = $data['private_key_file'] ?? null;
        $directory = 'private/aeat/certificates/'.$user->getKey().'/'.Str::uuid()->toString();
        $certificate = $this->certificateVault->storeEncryptedUpload($certificateFile, $directory, 'certificate.enc');
        $privateKey = $privateKeyFile
            ? $this->certificateVault->storeEncryptedUpload($privateKeyFile, $directory, 'private_key.enc')
            : null;

        return $user->aeatCertificateProfiles()->create([
            'name' => $data['name'],
            'certificate_format' => strtolower((string) $data['certificate_format']),
            'certificate_disk' => $certificate['disk'],
            'certificate_path' => $certificate['path'],
            'certificate_original_name' => $certificate['original_name'],
            'private_key_disk' => $privateKey['disk'] ?? null,
            'private_key_path' => $privateKey['path'] ?? null,
            'private_key_original_name' => $privateKey['original_name'] ?? null,
            'passphrase' => $data['passphrase'] ?? null,
            'meta' => [
                'certificate_mime_type' => $certificateFile->getClientMimeType(),
                'private_key_mime_type' => $privateKeyFile?->getClientMimeType(),
            ],
        ]);
    }

    /**
     * Create and start a new fiscal-data request.
     *
     * @param  array<string, mixed>  $data
     */
    public function startRequest(User $user, array $data): AeatFiscalDataRequest
    {
        $request = $user->aeatFiscalDataRequests()->create([
            'certificate_profile_id' => $data['certificate_profile_id'] ?? null,
            'precheck_certificate_profile_id' => $data['precheck_certificate_profile_id'] ?? null,
            'status' => $data['auth_method'] === 'clave_movil' ? 'preparing' : 'queued',
            'stage' => $data['auth_method'] === 'clave_movil' ? 'clave_authentication' : 'queued',
            'auth_method' => $data['auth_method'],
            'taxpayer_nif' => $data['taxpayer_nif'],
            'auth_nif' => $data['auth_nif'] ?? null,
            'pdp' => $data['pdp'] === 'S',
            'payload' => $this->buildPayload($data),
            'session_state' => $this->buildSessionState($data),
            'queued_at' => $data['auth_method'] === 'clave_movil' ? null : Carbon::now(),
        ]);

        if ($request->auth_method === 'clave_movil') {
            return $this->startClaveMovilFlow($request);
        }

        ProcessAeatFiscalDataRequest::dispatch($request->getKey());

        return $request;
    }

    /**
     * Submit the Cl@ve Movil PIN and enqueue the request.
     */
    public function submitClavePin(AeatFiscalDataRequest $request, string $pin): AeatFiscalDataRequest
    {
        if (! $request->isAwaitingPin()) {
            throw ValidationException::withMessages([
                'pin' => 'This request is not waiting for a Cl@ve Movil PIN.',
            ]);
        }

        $sessionState = $request->session_state ?? [];
        $sessionState['pin'] = $pin;

        $request->forceFill([
            'status' => 'queued',
            'stage' => 'clave_validate_pin',
            'session_state' => $sessionState,
            'queued_at' => Carbon::now(),
            'awaiting_pin_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'last_error_context' => null,
        ])->save();

        ProcessAeatFiscalDataRequest::dispatch($request->getKey());

        return $request->fresh();
    }

    /**
     * Re-queue a request when enough secure state is still available.
     */
    public function retryRequest(AeatFiscalDataRequest $request): AeatFiscalDataRequest
    {
        if (! $request->canRetry()) {
            throw ValidationException::withMessages([
                'request' => 'This request cannot be retried with the current secure state.',
            ]);
        }

        $request->forceFill([
            'status' => 'queued',
            'stage' => 'retry_queued',
            'queued_at' => Carbon::now(),
            'processing_at' => null,
            'downloaded_at' => null,
            'completed_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'last_error_context' => null,
        ])->save();

        ProcessAeatFiscalDataRequest::dispatch($request->getKey());

        return $request->fresh();
    }

    /**
     * Start the Cl@ve Movil challenge and persist the returned tokens securely.
     */
    protected function startClaveMovilFlow(AeatFiscalDataRequest $request): AeatFiscalDataRequest
    {
        try {
            $challenge = $this->httpClient->startClaveMovil($request->payload ?? []);
            $request->forceFill([
                'status' => 'awaiting_pin',
                'stage' => 'clave_pin_requested',
                'awaiting_pin_at' => Carbon::now(),
                'session_state' => array_merge($request->session_state ?? [], $challenge),
            ])->save();
        } catch (\Throwable $throwable) {
            $this->processor->recordFailure($request->getKey(), $throwable, 1, 1, false, 'failed');
            throw $throwable;
        }

        return $request->fresh();
    }

    /**
     * Build the request payload that can be shown safely in the UI.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildPayload(array $data): array
    {
        return array_filter([
            'taxpayer_nif' => $data['taxpayer_nif'],
            'auth_nif' => $data['auth_nif'] ?? null,
            'fecha' => $data['fecha'] ?? null,
            'soporte' => $data['soporte'] ?? null,
            'pdp' => $data['pdp'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Build the encrypted session state for sensitive request data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildSessionState(array $data): array
    {
        if (($data['auth_method'] ?? null) !== 'reference') {
            return [];
        }

        return [
            'reference_hash' => hash('sha512', (string) ($data['reference_code'] ?? '')),
        ];
    }
}
