<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeatFiscalDataError extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aeat_fiscal_data_request_id',
        'stage',
        'code',
        'message',
        'details',
        'retryable',
        'attempt',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'retryable' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the request that owns the error.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AeatFiscalDataRequest::class, 'aeat_fiscal_data_request_id');
    }
}
