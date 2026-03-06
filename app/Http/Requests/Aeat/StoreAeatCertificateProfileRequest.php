<?php

namespace App\Http\Requests\Aeat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAeatCertificateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'certificate_format' => ['required', Rule::in(['p12', 'pfx', 'pem'])],
            'certificate_file' => ['required', 'file', 'max:20480'],
            'private_key_file' => ['nullable', 'file', 'max:20480'],
            'passphrase' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var UploadedFile|null $certificateFile */
            $certificateFile = $this->file('certificate_file');
            if (! $certificateFile instanceof UploadedFile) {
                return;
            }

            $format = strtolower((string) $this->input('certificate_format'));
            $passphrase = (string) ($this->input('passphrase') ?? '');

            if (! $this->certificateExtensionMatches($certificateFile, $format)) {
                $validator->errors()->add('certificate_file', 'The certificate file extension does not match the selected format.');

                return;
            }

            if (in_array($format, ['p12', 'pfx'], true) && function_exists('openssl_pkcs12_read') && ! $this->isValidPkcs12($certificateFile, $passphrase)) {
                $validator->errors()->add('certificate_file', 'The PKCS#12 certificate could not be read. Check that the file is valid and that the passphrase is correct.');

                return;
            }

            if ($format === 'pem' && function_exists('openssl_x509_read') && ! $this->isValidPemCertificate($certificateFile)) {
                $validator->errors()->add('certificate_file', 'The PEM certificate could not be read.');

                return;
            }

            /** @var UploadedFile|null $privateKeyFile */
            $privateKeyFile = $this->file('private_key_file');
            if ($format === 'pem' && $privateKeyFile instanceof UploadedFile && function_exists('openssl_pkey_get_private') && ! $this->isValidPemPrivateKey($privateKeyFile, $passphrase)) {
                $validator->errors()->add('private_key_file', 'The PEM private key could not be read with the provided passphrase.');
            }
        });
    }

    /**
     * Ensure the selected format matches the uploaded file extension.
     */
    protected function certificateExtensionMatches(UploadedFile $file, string $format): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($format) {
            'p12', 'pfx' => in_array($extension, ['p12', 'pfx'], true),
            'pem' => in_array($extension, ['pem', 'crt', 'cer'], true),
            default => false,
        };
    }

    /**
     * Verify that a PKCS#12 certificate can be opened.
     */
    protected function isValidPkcs12(UploadedFile $file, string $passphrase): bool
    {
        $contents = $file->getContent();
        if (! is_string($contents) || $contents === '') {
            return false;
        }

        return @openssl_pkcs12_read($contents, $certificates, $passphrase);
    }

    /**
     * Verify that a PEM certificate can be opened.
     */
    protected function isValidPemCertificate(UploadedFile $file): bool
    {
        $contents = $file->getContent();
        if (! is_string($contents) || $contents === '') {
            return false;
        }

        $certificate = @openssl_x509_read($contents);
        if ($certificate === false) {
            return false;
        }

        openssl_x509_free($certificate);

        return true;
    }

    /**
     * Verify that a PEM private key can be opened.
     */
    protected function isValidPemPrivateKey(UploadedFile $file, string $passphrase): bool
    {
        $contents = $file->getContent();
        if (! is_string($contents) || $contents === '') {
            return false;
        }

        $privateKey = @openssl_pkey_get_private($contents, $passphrase);
        if ($privateKey === false) {
            return false;
        }

        openssl_pkey_free($privateKey);

        return true;
    }
}