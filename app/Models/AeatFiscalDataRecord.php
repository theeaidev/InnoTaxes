<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeatFiscalDataRecord extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aeat_fiscal_data_request_id',
        'aeat_fiscal_data_file_id',
        'line_number',
        'record_type',
        'record_code',
        'layout_key',
        'line_length',
        'raw_line',
        'normalized_data',
        'parse_warnings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'normalized_data' => 'array',
            'parse_warnings' => 'array',
        ];
    }

    /**
     * Get the request that owns the record.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AeatFiscalDataRequest::class, 'aeat_fiscal_data_request_id');
    }

    /**
     * Get the file the record belongs to.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(AeatFiscalDataFile::class, 'aeat_fiscal_data_file_id');
    }
}
