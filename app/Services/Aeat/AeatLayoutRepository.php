<?php

namespace App\Services\Aeat;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AeatLayoutRepository
{
    /**
     * Load the normalized AEAT layout definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $path = (string) config('aeat.layout.json_path');
        $cacheKey = 'aeat.layout.'.md5($path);

        return Cache::rememberForever($cacheKey, function () use ($path): array {
            if (! is_file($path)) {
                throw new RuntimeException("AEAT layout file not found at [{$path}].");
            }

            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return $data['layouts'] ?? [];
        });
    }
}
