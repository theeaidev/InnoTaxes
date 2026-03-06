<?php

namespace App\Services\Aeat;

use App\Models\AeatCertificateProfile;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AeatHttpClient
{
    /**
     * Create a new HTTP client instance.
     */
    public function __construct(
        protected AeatCertificateVault $certificateVault,
        protected AeatCookieJarSerializer $cookieJarSerializer,
    ) {
    }

    /**
     * Check whether the taxpayer has ratified the fiscal domicile.
     *
     * @return array<string, mixed>
     */
    public function checkRatification(AeatCertificateProfile $profile, string $taxpayerNif): array
    {
        return $this->certificateVault->withMaterializedProfile($profile, function (AeatClientCertificateMaterial $material) use ($taxpayerNif): array {
            $response = $this->baseRequest(certificate: $material)
                ->asForm()
                ->acceptJson()
                ->post((string) config('aeat.urls.ratification_check'), [
                    'EJERCICIO' => (string) config('aeat.exercise'),
                    'NIF' => $taxpayerNif,
                ]);

            $this->ensureHttpOk($response, 'ratification_check');
            $json = $response->json();

            if (($json['status'] ?? null) !== 'OK') {
                throw new AeatIntegrationException(
                    message: (string) ($json['mensaje'] ?? 'The ratification status could not be obtained.'),
                    stage: 'ratification_check',
                    errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                    context: ['response' => $json],
                    retryable: false,
                );
            }

            return [
                'domicile_ratified' => (bool) data_get($json, 'respuesta.domicilioRatificado'),
                'response' => $json,
            ];
        });
    }

    /**
     * Authenticate a reference flow and return the cookie jar.
     */
    public function authenticateReference(string $authNif, string $referenceHash): CookieJar
    {
        $cookieJar = new CookieJar();
        $response = $this->baseRequest(cookieJar: $cookieJar)
            ->asForm()
            ->acceptJson()
            ->post((string) config('aeat.urls.reference_auth'), [
                'nif' => $authNif,
                'hnr12' => $referenceHash,
            ]);

        $json = $this->assertJsonOk($response, 'reference_auth', false);

        if (($json['status'] ?? null) !== 'OK') {
            throw new AeatIntegrationException(
                message: (string) ($json['mensaje'] ?? 'The AEAT reference validation failed.'),
                stage: 'reference_auth',
                errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                context: ['response' => $json],
                retryable: false,
            );
        }

        return $cookieJar;
    }

    /**
     * Start the Cl@ve Movil flow and request the SMS PIN.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function startClaveMovil(array $payload): array
    {
        $cookieJar = new CookieJar();
        $requestPayload = [
            'NIF' => $payload['auth_nif'],
            'ref' => (string) config('aeat.urls.clave_movil.ref_path'),
            'formatoError' => 'json',
        ];

        if (! empty($payload['fecha'])) {
            $requestPayload['FECHA'] = $payload['fecha'];
        }

        if (! empty($payload['soporte'])) {
            $requestPayload['SOPORTE'] = $payload['soporte'];
        }

        $authResponse = $this->baseRequest(cookieJar: $cookieJar, allowRedirects: false)
            ->asForm()
            ->post((string) config('aeat.urls.clave_movil.authenticate'), $requestPayload);

        if (! $authResponse->successful() && ! $authResponse->redirect()) {
            $this->ensureHttpOk($authResponse, 'clave_authentication');
        }

        if ($authResponse->successful() && $this->looksLikeJson($authResponse)) {
            $json = $authResponse->json();
            if (($json['status'] ?? null) === 'KO') {
                throw new AeatIntegrationException(
                    message: (string) ($json['mensaje'] ?? 'Cl@ve Movil authentication failed.'),
                    stage: 'clave_authentication',
                    errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                    context: ['response' => $json],
                    retryable: false,
                );
            }
        }

        $smsResponse = $this->baseRequest(cookieJar: $cookieJar)
            ->acceptJson()
            ->post((string) config('aeat.urls.clave_movil.request_sms'));

        $json = $this->assertJsonOk($smsResponse, 'clave_request_sms', false);

        if (($json['status'] ?? null) !== 'OK') {
            throw new AeatIntegrationException(
                message: (string) ($json['mensaje'] ?? 'The Cl@ve Movil PIN could not be requested.'),
                stage: 'clave_request_sms',
                errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                context: ['response' => $json],
                retryable: false,
            );
        }

        return [
            'cookies' => $this->cookieJarSerializer->serialize($cookieJar),
            'token_clave_movil_sms' => data_get($json, 'respuesta.tokenClaveMovilSms'),
            'timestamp_alta_sms' => data_get($json, 'respuesta.timeStampAltaSms'),
            'masked_mobile' => data_get($json, 'respuesta.movil'),
            'raw_response' => $json,
        ];
    }

    /**
     * Validate a Cl@ve Movil PIN and return the updated cookie jar.
     *
     * @param  array<string, mixed>  $sessionState
     */
    public function validateClaveMovilPin(array $sessionState, string $pin): CookieJar
    {
        $cookieJar = $this->cookieJarSerializer->deserialize($sessionState['cookies'] ?? []);
        $response = $this->baseRequest(cookieJar: $cookieJar)
            ->acceptJson()
            ->asForm()
            ->post((string) config('aeat.urls.clave_movil.validate_pin'), [
                'timeStampAltaSms' => $sessionState['timestamp_alta_sms'] ?? null,
                'tokenClaveMovilSms' => $sessionState['token_clave_movil_sms'] ?? null,
                'pinAcceso' => $pin,
            ]);

        $json = $this->assertJsonOk($response, 'clave_validate_pin', false);

        if (($json['status'] ?? null) !== 'OK') {
            throw new AeatIntegrationException(
                message: (string) ($json['mensaje'] ?? 'The Cl@ve Movil PIN is not valid.'),
                stage: 'clave_validate_pin',
                errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                context: ['response' => $json],
                retryable: false,
            );
        }

        return $cookieJar;
    }

    /**
     * Download fiscal data using client-certificate authentication.
     */
    public function downloadWithCertificate(AeatCertificateProfile $profile, string $taxpayerNif, bool $includePersonalData): string
    {
        return $this->certificateVault->withMaterializedProfile($profile, function (AeatClientCertificateMaterial $material) use ($taxpayerNif, $includePersonalData): string {
            $response = $this->baseRequest(certificate: $material)
                ->get((string) config('aeat.urls.certificate_download'), [
                    'nif' => $taxpayerNif,
                    'pdp' => $includePersonalData ? 'S' : 'N',
                ]);

            return $this->unwrapDownloadResponse($response, 'certificate_download');
        });
    }

    /**
     * Download fiscal data using an authenticated cookie jar.
     */
    public function downloadWithCookies(string $url, CookieJar $cookieJar, string $taxpayerNif, bool $includePersonalData): string
    {
        $response = $this->baseRequest(cookieJar: $cookieJar)
            ->get($url, [
                'nif' => $taxpayerNif,
                'pdp' => $includePersonalData ? 'S' : 'N',
            ]);

        return $this->unwrapDownloadResponse($response, 'fiscal_data_download');
    }

    /**
     * Create a pre-configured pending request.
     */
    protected function baseRequest(?CookieJar $cookieJar = null, ?AeatClientCertificateMaterial $certificate = null, bool $allowRedirects = true): PendingRequest
    {
        $options = [
            'allow_redirects' => $allowRedirects,
        ];

        if ($cookieJar) {
            $options['cookies'] = $cookieJar;
        }

        if ($certificate) {
            $options = array_replace_recursive($options, $this->certificateOptions($certificate));
        }

        return Http::timeout((int) config('aeat.timeouts.request'))
            ->connectTimeout((int) config('aeat.timeouts.connect'))
            ->retry(2, 500, throw: false)
            ->withOptions($options);
    }

    /**
     * Build the options needed for client-certificate authentication.
     *
     * @return array<string, mixed>
     */
    protected function certificateOptions(AeatClientCertificateMaterial $certificate): array
    {
        $format = strtolower($certificate->format);

        if (in_array($format, ['p12', 'pfx'], true)) {
            return [
                'curl' => [
                    CURLOPT_SSLCERT => $certificate->certificatePath,
                    CURLOPT_SSLCERTTYPE => 'P12',
                    CURLOPT_SSLCERTPASSWD => $certificate->passphrase ?? '',
                ],
            ];
        }

        $options = [
            'cert' => [$certificate->certificatePath, $certificate->passphrase ?? ''],
        ];

        if ($certificate->privateKeyPath) {
            $options['ssl_key'] = [$certificate->privateKeyPath, $certificate->passphrase ?? ''];
        }

        return $options;
    }

    /**
     * Ensure the response returned a successful HTTP status code.
     */
    protected function ensureHttpOk(Response $response, string $stage): void
    {
        if ($response->successful()) {
            return;
        }

        throw new AeatIntegrationException(
            message: sprintf('AEAT returned HTTP %s for stage [%s].', $response->status(), $stage),
            stage: $stage,
            context: ['status' => $response->status(), 'body' => $response->body()],
            retryable: $response->serverError(),
        );
    }

    /**
     * Assert that a response contains JSON data.
     *
     * @return array<string, mixed>
     */
    protected function assertJsonOk(Response $response, string $stage, bool $retryable): array
    {
        $this->ensureHttpOk($response, $stage);
        $json = $response->json();

        if (! is_array($json)) {
            throw new AeatIntegrationException(
                message: 'AEAT did not return a valid JSON payload.',
                stage: $stage,
                context: ['body' => $response->body()],
                retryable: $retryable,
            );
        }

        return $json;
    }

    /**
     * Unwrap a fiscal-data download response and raise known errors.
     */
    protected function unwrapDownloadResponse(Response $response, string $stage): string
    {
        $this->ensureHttpOk($response, $stage);
        $body = trim($response->body());

        if ($body === '') {
            throw new AeatIntegrationException(
                message: 'AEAT returned an empty response body.',
                stage: $stage,
                retryable: true,
            );
        }

        if ($this->looksLikeJson($response)) {
            $json = $response->json();
            if (is_array($json) && ($json['status'] ?? null) === 'KO') {
                throw new AeatIntegrationException(
                    message: (string) ($json['mensaje'] ?? 'AEAT returned an application error.'),
                    stage: $stage,
                    errorCode: isset($json['codigo_error']) ? (string) $json['codigo_error'] : null,
                    context: ['response' => $json],
                    retryable: false,
                );
            }
        }

        if (preg_match('/^Error\s+(\d+)\s+(.+)$/i', $body, $matches)) {
            throw new AeatIntegrationException(
                message: trim($matches[2]),
                stage: $stage,
                errorCode: $matches[1],
                context: ['body' => $body],
                retryable: false,
            );
        }

        $normalizedBody = AeatDocumentHelper::normalizeMessage($body);
        foreach ((array) config('aeat.download_error_messages') as $message) {
            $normalizedMessage = AeatDocumentHelper::normalizeMessage($message);
            if (str_contains($normalizedBody, $normalizedMessage)) {
                $errorCode = str_contains($normalizedMessage, 'error 5001') ? '5001' : null;
                throw new AeatIntegrationException(
                    message: trim($message),
                    stage: $stage,
                    errorCode: $errorCode,
                    context: ['body' => $body],
                    retryable: false,
                );
            }
        }

        return $body;
    }

    /**
     * Determine whether the response content looks like JSON.
     */
    protected function looksLikeJson(Response $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = trim($response->body());

        return str_contains($contentType, 'json') || str_starts_with($body, '{') || str_starts_with($body, '[');
    }
}
