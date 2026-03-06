<?php

namespace App\Services\Aeat;

use App\Models\AeatCertificateProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AeatCertificateVault
{
    /**
     * Store an uploaded certificate file encrypted at rest.
     *
     * @return array{disk: string, path: string, original_name: string}
     */
    public function storeEncryptedUpload(UploadedFile $file, string $directory, string $filename): array
    {
        $disk = 'local';
        $path = trim($directory, '/').'/'.$filename;
        $payload = Crypt::encryptString(base64_encode($file->getContent()));

        Storage::disk($disk)->put($path, $payload);

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Materialize an encrypted certificate profile on disk for a single request.
     */
    public function materialize(AeatCertificateProfile $profile): AeatClientCertificateMaterial
    {
        $tempDirectory = storage_path('app/private/aeat/tmp/'.Str::uuid()->toString());
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $certificatePath = $this->decryptToFile($profile->certificate_disk, $profile->certificate_path, $tempDirectory.'/certificate.'.$profile->certificate_format);
        $privateKeyPath = null;

        if ($profile->private_key_path) {
            $privateKeyPath = $this->decryptToFile(
                $profile->private_key_disk ?? 'local',
                $profile->private_key_path,
                $tempDirectory.'/private-key.pem',
            );
        }

        return new AeatClientCertificateMaterial(
            temporaryDirectory: $tempDirectory,
            certificatePath: $certificatePath,
            privateKeyPath: $privateKeyPath,
            format: $profile->certificate_format,
            passphrase: $profile->passphrase,
        );
    }

    /**
     * Execute a callback while a certificate is materialized.
     */
    public function withMaterializedProfile(AeatCertificateProfile $profile, callable $callback): mixed
    {
        $material = $this->materialize($profile);

        try {
            return $callback($material);
        } finally {
            $this->cleanup($material);
        }
    }

    /**
     * Remove temporary certificate files.
     */
    public function cleanup(AeatClientCertificateMaterial $material): void
    {
        foreach ([$material->certificatePath, $material->privateKeyPath] as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir($material->temporaryDirectory)) {
            @rmdir($material->temporaryDirectory);
        }
    }

    /**
     * Decrypt a stored file to a target path.
     */
    protected function decryptToFile(string $disk, string $sourcePath, string $targetPath): string
    {
        $encrypted = Storage::disk($disk)->get($sourcePath);
        $decrypted = base64_decode(Crypt::decryptString($encrypted), true);

        if ($decrypted === false) {
            throw new AeatIntegrationException(
                message: 'The encrypted certificate payload could not be decoded.',
                stage: 'certificate_materialization',
                retryable: false,
            );
        }

        file_put_contents($targetPath, $decrypted);

        return $targetPath;
    }
}
