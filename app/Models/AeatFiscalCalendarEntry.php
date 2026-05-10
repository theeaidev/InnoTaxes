<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeatFiscalCalendarEntry extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aeat_fiscal_calendar_id',
        'model_code',
        'model_name',
        'category',
        'period_label',
        'base_due_at',
        'due_at',
        'status',
        'snoozed_until',
        'completed_at',
        'source_label',
        'source_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_due_at' => 'datetime',
            'due_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent calendar.
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(AeatFiscalCalendar::class, 'aeat_fiscal_calendar_id');
    }

    /**
     * Determine whether the entry has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine whether the entry is snoozed.
     */
    public function isSnoozed(): bool
    {
        return $this->status === 'snoozed' && $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }

    /**
     * Return the number of days remaining until the deadline.
     */
    public function daysRemaining(?CarbonInterface $reference = null): int
    {
        $reference ??= now();

        return $reference->copy()->startOfDay()->diffInDays($this->due_at->copy()->startOfDay(), false);
    }

    /**
     * Determine whether the entry is overdue.
     */
    public function isOverdue(?CarbonInterface $reference = null): bool
    {
        if ($this->isCompleted() || $this->isSnoozed()) {
            return false;
        }

        return $this->daysRemaining($reference) < 0;
    }

    /**
     * Determine whether the entry falls inside the due-soon window.
     */
    public function isDueSoon(?CarbonInterface $reference = null): bool
    {
        if ($this->isCompleted() || $this->isSnoozed()) {
            return false;
        }

        $daysRemaining = $this->daysRemaining($reference);

        return $daysRemaining >= 0 && $daysRemaining <= (int) config('aeat_calendar.risk_thresholds.amber_days', 30);
    }

    /**
     * Return the semantic risk state used by the UI.
     */
    public function riskState(?CarbonInterface $reference = null): string
    {
        if ($this->isCompleted()) {
            return 'success';
        }

        if ($this->isSnoozed()) {
            return 'warning';
        }

        if ($this->isOverdue($reference)) {
            return 'danger';
        }

        if ($this->isDueSoon($reference)) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Human-readable risk label.
     */
    public function riskLabel(?CarbonInterface $reference = null): string
    {
        return match ($this->riskState($reference)) {
            'success' => $this->isCompleted() ? 'Completado' : 'En plazo',
            'warning' => $this->isSnoozed() ? 'Aplazado' : 'Próximo',
            'danger' => 'Vencido',
            default => 'Sin riesgo',
        };
    }

    /**
     * Bootstrap badge class for the risk state.
     */
    public function riskBadgeClass(?CarbonInterface $reference = null): string
    {
        return match ($this->riskState($reference)) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Bootstrap badge class for the workflow status.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'snoozed' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Human-readable workflow status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Completado',
            'snoozed' => 'Aplazado',
            default => 'Pendiente',
        };
    }

    /**
     * Human-readable deadline caption.
     */
    public function dueCaption(?CarbonInterface $reference = null): string
    {
        if ($this->isCompleted()) {
            return 'Ya completado';
        }

        if ($this->isSnoozed() && $this->snoozed_until) {
            return 'Aplazado hasta '.$this->snoozed_until->format('d/m/Y');
        }

        $daysRemaining = $this->daysRemaining($reference);

        return match (true) {
            $daysRemaining === 0 => 'Vence hoy',
            $daysRemaining === 1 => 'Vence mañana',
            $daysRemaining > 1 => 'Vence en '.$daysRemaining.' días',
            $daysRemaining === -1 => 'Venció ayer',
            default => 'Venció hace '.abs($daysRemaining).' días',
        };
    }

    /**
     * Determine whether the due date was adjusted because of a non-business day.
     */
    public function wasAdjusted(): bool
    {
        if ($this->base_due_at === null || $this->due_at === null) {
            return false;
        }

        return $this->base_due_at->copy()->startOfDay()->ne($this->due_at->copy()->startOfDay());
    }
}
