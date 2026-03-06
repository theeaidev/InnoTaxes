<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AeatFiscalDataRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'certificate_profile_id',
        'precheck_certificate_profile_id',
        'status',
        'stage',
        'auth_method',
        'taxpayer_nif',
        'auth_nif',
        'pdp',
        'domicile_status',
        'payload',
        'session_state',
        'attempts',
        'last_error_code',
        'last_error_message',
        'last_error_context',
        'queued_at',
        'processing_at',
        'awaiting_pin_at',
        'last_checked_domicile_at',
        'downloaded_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pdp' => 'boolean',
            'payload' => 'array',
            'session_state' => 'encrypted:array',
            'last_error_context' => 'array',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'awaiting_pin_at' => 'datetime',
            'last_checked_domicile_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the certificate profile used for authentication.
     */
    public function certificateProfile(): BelongsTo
    {
        return $this->belongsTo(AeatCertificateProfile::class, 'certificate_profile_id');
    }

    /**
     * Get the optional certificate profile used for the ratification pre-check.
     */
    public function precheckCertificateProfile(): BelongsTo
    {
        return $this->belongsTo(AeatCertificateProfile::class, 'precheck_certificate_profile_id');
    }

    /**
     * Get the raw files generated for the request.
     */
    public function files(): HasMany
    {
        return $this->hasMany(AeatFiscalDataFile::class);
    }

    /**
     * Get the parsed records for the request.
     */
    public function records(): HasMany
    {
        return $this->hasMany(AeatFiscalDataRecord::class);
    }

    /**
     * Get the recorded errors for the request.
     */
    public function errors(): HasMany
    {
        return $this->hasMany(AeatFiscalDataError::class);
    }

    /**
     * Determine whether the request is waiting for the Cl@ve PIN.
     */
    public function isAwaitingPin(): bool
    {
        return $this->auth_method === 'clave_movil' && $this->status === 'awaiting_pin';
    }

    /**
     * Determine whether the request can be retried with the current secure state.
     */
    public function canRetry(): bool
    {
        if ($this->status === 'completed' || ! in_array($this->status, ['failed', 'retrying'], true)) {
            return false;
        }

        return match ($this->auth_method) {
            'certificate' => $this->certificate_profile_id !== null,
            'reference' => filled(data_get($this->session_state, 'reference_hash')),
            'clave_movil' => filled(data_get($this->session_state, 'pin')),
            default => false,
        };
    }
}
