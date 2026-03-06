<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AeatFiscalDataFile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aeat_fiscal_data_request_id',
        'disk',
        'path',
        'filename',
        'sha256',
        'bytes',
        'line_count',
        'record_count',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Get the request that owns the file.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AeatFiscalDataRequest::class, 'aeat_fiscal_data_request_id');
    }

    /**
     * Get the parsed records extracted from this file.
     */
    public function records(): HasMany
    {
        return $this->hasMany(AeatFiscalDataRecord::class);
    }
}
