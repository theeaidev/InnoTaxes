<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AeatFiscalCalendar extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'taxpayer_nif',
        'exercise',
        'regime',
        'enabled_models',
        'is_default',
        'last_generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled_models' => 'array',
            'exercise' => 'integer',
            'is_default' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the calendar.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the generated deadlines for the calendar.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(AeatFiscalCalendarEntry::class, 'aeat_fiscal_calendar_id');
    }
}
