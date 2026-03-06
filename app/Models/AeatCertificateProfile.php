<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AeatCertificateProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'certificate_format',
        'certificate_disk',
        'certificate_path',
        'certificate_original_name',
        'private_key_disk',
        'private_key_path',
        'private_key_original_name',
        'passphrase',
        'meta',
        'last_used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'passphrase' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fiscal-data requests using this profile for authentication.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(AeatFiscalDataRequest::class, 'certificate_profile_id');
    }

    /**
     * Determine whether the profile includes a separate private key file.
     */
    public function hasPrivateKey(): bool
    {
        return filled($this->private_key_path);
    }
}
