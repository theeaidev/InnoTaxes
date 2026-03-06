<?php

namespace App\Services\Aeat;

use Illuminate\Support\Str;

class AeatDocumentHelper
{
    /**
     * Normalize a NIF/NIE value.
     */
    public static function sanitizeNif(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a reference code.
     */
    public static function sanitizeReference(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a support number.
     */
    public static function sanitizeSupport(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize a FECHA value keeping separators accepted by AEAT.
     */
    public static function sanitizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Determine whether the document looks like a NIE.
     */
    public static function looksLikeNie(?string $nif): bool
    {
        return $nif !== null && (bool) preg_match('/^[XYZ][0-9]{7}[A-Z0-9]$/', $nif);
    }

    /**
     * Convert a human label into a stable snake_case key.
     */
    public static function fieldKey(string $label): string
    {
        $key = Str::of($label)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return $key !== '' ? $key : 'field';
    }

    /**
     * Normalize messages for defensive comparisons.
     */
    public static function normalizeMessage(string $message): string
    {
        return Str::of($message)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }
}
