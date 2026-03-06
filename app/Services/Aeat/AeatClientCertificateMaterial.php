<?php

namespace App\Services\Aeat;

class AeatClientCertificateMaterial
{
    /**
     * Create a new materialized certificate payload.
     */
    public function __construct(
        public readonly string $temporaryDirectory,
        public readonly string $certificatePath,
        public readonly ?string $privateKeyPath,
        public readonly string $format,
        public readonly ?string $passphrase,
    ) {
    }
}
