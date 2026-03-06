<?php

namespace App\Services\Aeat;

class AeatFiscalDataParser
{
    /**
     * Create a new parser instance.
     */
    public function __construct(protected AeatLayoutRepository $layoutRepository)
    {
    }

    /**
     * Parse a raw AEAT payload into structured records.
     *
     * @return array{records: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function parse(string $payload): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($payload));
        $records = [];
        $summary = [
            'total_records' => 0,
            'by_type' => [],
            'by_code' => [],
        ];

        foreach ($lines as $index => $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $record = $this->parseLine($line, $index + 1);
            $records[] = $record;
            $summary['total_records']++;

            $summary['by_type'][$record['record_type']] = ($summary['by_type'][$record['record_type']] ?? 0) + 1;
            if ($record['record_code']) {
                $summary['by_code'][$record['record_code']] = ($summary['by_code'][$record['record_code']] ?? 0) + 1;
            }
        }

        ksort($summary['by_type']);
        ksort($summary['by_code']);

        return [
            'records' => $records,
            'summary' => $summary,
        ];
    }

    /**
     * Parse a single fixed-width line.
     *
     * @return array<string, mixed>
     */
    protected function parseLine(string $line, int $lineNumber): array
    {
        $layout = $this->resolveLayout($line);
        $recordType = substr($line, 0, 1);
        $recordType = $recordType === false || $recordType === '' ? 'unknown' : $recordType;
        $recordCode = strlen($line) >= 8 ? trim(substr($line, 1, 7)) : null;
        $warnings = [];

        if ($layout === null) {
            $warnings[] = 'No layout was found for the record.';

            return [
                'line_number' => $lineNumber,
                'record_type' => $recordType,
                'record_code' => $recordCode ?: null,
                'layout_key' => null,
                'line_length' => strlen($line),
                'raw_line' => $line,
                'normalized_data' => [
                    'sheet' => null,
                    'fields' => [],
                    'variant' => null,
                ],
                'warnings' => $warnings,
            ];
        }

        $fields = $this->parseFields($line, $layout['fields']);
        $variant = $this->parseVariant($line, $layout, $fields, $warnings);
        $actualCode = $this->extractRecordCode($line, $layout) ?? $recordCode;

        return [
            'line_number' => $lineNumber,
            'record_type' => $recordType,
            'record_code' => $actualCode ?: null,
            'layout_key' => $layout['key'],
            'line_length' => strlen($line),
            'raw_line' => $line,
            'normalized_data' => [
                'sheet' => $layout['sheet'],
                'fields' => $fields,
                'variant' => $variant,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Resolve the layout for a fixed-width line.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveLayout(string $line): ?array
    {
        $recordType = substr($line, 0, 1);
        $candidates = array_values(array_filter(
            $this->layoutRepository->all(),
            fn (array $layout): bool => ($layout['record_type'] ?? null) === $recordType,
        ));

        usort($candidates, function (array $left, array $right): int {
            return strlen((string) ($right['match']['value'] ?? '')) <=> strlen((string) ($left['match']['value'] ?? ''));
        });

        foreach ($candidates as $layout) {
            $match = $layout['match'] ?? ['kind' => 'record_type_only'];
            $kind = $match['kind'] ?? 'record_type_only';
            if ($kind === 'record_type_only') {
                return $layout;
            }

            $position = (int) ($match['position'] ?? 0);
            $length = (int) ($match['length'] ?? 0);
            if ($position <= 0 || $length <= 0) {
                continue;
            }

            $value = substr($line, $position - 1, $length);
            $expected = (string) ($match['value'] ?? '');

            if ($kind === 'exact' && trim($value) === $expected) {
                return $layout;
            }

            if ($kind === 'prefix' && str_starts_with(trim($value), $expected)) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * Parse the fields described by a layout.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, array<string, mixed>>
     */
    protected function parseFields(string $line, array $definitions): array
    {
        $fields = [];

        foreach ($definitions as $definition) {
            $key = AeatDocumentHelper::fieldKey((string) ($definition['label'] ?? 'field'));
            $position = $definition['position'] ?? null;
            $length = $definition['length'] ?? null;
            $raw = null;

            if (is_int($position) && is_int($length) && $length > 0) {
                $raw = substr($line, $position - 1, $length);
            }

            $fields[$key] = [
                'label' => $definition['label'] ?? $key,
                'position' => $position,
                'length' => $length,
                'length_expression' => $definition['length_expression'] ?? null,
                'description' => $definition['description'] ?? null,
                'raw' => $raw,
                'value' => $raw === null ? null : trim($raw),
            ];
        }

        return $fields;
    }

    /**
     * Parse an optional variant section for the current record.
     *
     * @param  array<string, array<string, mixed>>  $baseFields
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>|null
     */
    protected function parseVariant(string $line, array $layout, array $baseFields, array &$warnings): ?array
    {
        if (empty($layout['variants'])) {
            return null;
        }

        $selectorFieldLabel = $layout['variants'][0]['selector_field_label'] ?? null;
        if (! $selectorFieldLabel) {
            return null;
        }

        $selectorKey = AeatDocumentHelper::fieldKey((string) $selectorFieldLabel);
        $selectorValue = $baseFields[$selectorKey]['value'] ?? null;

        foreach ($layout['variants'] as $variant) {
            if ((string) ($variant['value'] ?? '') !== (string) $selectorValue) {
                continue;
            }

            return [
                'heading' => $variant['heading'] ?? null,
                'selector_field_label' => $selectorFieldLabel,
                'selector_value' => $selectorValue,
                'target_field_label' => $variant['target_field_label'] ?? null,
                'fields' => $this->parseFields($line, $variant['fields'] ?? []),
            ];
        }

        $warnings[] = 'The record requires a variant definition that was not found.';

        return null;
    }

    /**
     * Extract the record code using the matched layout definition.
     */
    protected function extractRecordCode(string $line, array $layout): ?string
    {
        $match = $layout['match'] ?? [];
        $position = $match['position'] ?? null;
        $length = $match['length'] ?? null;

        if (! is_int($position) || ! is_int($length) || $position <= 0 || $length <= 0) {
            return null;
        }

        return trim(substr($line, $position - 1, $length));
    }
}
