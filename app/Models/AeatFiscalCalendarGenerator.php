<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class AeatFiscalCalendarGenerator
{
    public function generateCalendar(AeatFiscalCalendar $calendar): void
    {
        $calendar->entries()->delete();
        $calendar->entries()->createMany($this->buildEntries($calendar));
        $calendar->forceFill(['last_generated_at' => now()])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildEntries(AeatFiscalCalendar $calendar): array
    {
        $modelCatalog = config('aeat_calendar.models', []);
        $selectedModels = $calendar->enabled_models ?: $this->defaultModelsForRegime($calendar->regime);
        $selectedModels = array_values(array_unique(array_filter((array) $selectedModels)));

        $entries = [];

        foreach ($selectedModels as $modelCode) {
            if (! isset($modelCatalog[$modelCode]) || ! is_array($modelCatalog[$modelCode])) {
                continue;
            }

            foreach ($this->buildEntriesForModel($calendar, $modelCatalog[$modelCode]) as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, static function (array $left, array $right): int {
            return $left['due_at']->getTimestamp() <=> $right['due_at']->getTimestamp();
        });

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    public function defaultModelsForRegime(string $regime): array
    {
        return config("aeat_calendar.regime_defaults.$regime", config('aeat_calendar.regime_defaults.default', []));
    }

    public function adjustToBusinessDay(CarbonInterface $date): CarbonImmutable
    {
        $adjusted = CarbonImmutable::instance($date)->startOfDay();

        while ($this->isNonBusinessDay($adjusted)) {
            $adjusted = $adjusted->addDay();
        }

        return $adjusted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildEntriesForModel(AeatFiscalCalendar $calendar, array $model): array
    {
        $schedule = $model['schedule'] ?? [];
        $type = $schedule['type'] ?? 'quarterly';

        if ($type === 'annual') {
            return [$this->buildAnnualEntry($calendar, $model, $schedule)];
        }

        return $this->buildQuarterlyEntries($calendar, $model, $schedule);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildQuarterlyEntries(AeatFiscalCalendar $calendar, array $model, array $schedule): array
    {
        $exercise = (int) $calendar->exercise;
        $quarterDay = (int) ($schedule['quarter_day'] ?? 20);
        $q4Day = (int) ($schedule['q4_day'] ?? $quarterDay);
        $periods = [
            ['due_year' => $exercise, 'due_month' => 4, 'due_day' => $quarterDay, 'label' => '1T '.$exercise],
            ['due_year' => $exercise, 'due_month' => 7, 'due_day' => $quarterDay, 'label' => '2T '.$exercise],
            ['due_year' => $exercise, 'due_month' => 10, 'due_day' => $quarterDay, 'label' => '3T '.$exercise],
            ['due_year' => $exercise + 1, 'due_month' => 1, 'due_day' => $q4Day, 'label' => '4T '.$exercise],
        ];

        $entries = [];

        foreach ($periods as $period) {
            $entries[] = $this->calendarEntryPayload(
                $model,
                $calendar,
                $period['label'],
                $period['due_year'],
                $period['due_month'],
                $period['due_day']
            );
        }

        return $entries;
    }

    protected function buildAnnualEntry(AeatFiscalCalendar $calendar, array $model, array $schedule): array
    {
        $exercise = (int) $calendar->exercise;
        $dueYear = $exercise + 1;
        $dueMonth = (int) ($schedule['due_month'] ?? 1);
        $dueDay = (int) ($schedule['due_day'] ?? 30);

        return $this->calendarEntryPayload($model, $calendar, 'Ejercicio '.$exercise, $dueYear, $dueMonth, $dueDay);
    }

    /**
     * @return array<string, mixed>
     */
    protected function calendarEntryPayload(array $model, AeatFiscalCalendar $calendar, string $periodLabel, int $dueYear, int $dueMonth, int $dueDay): array
    {
        $baseDueAt = CarbonImmutable::create($dueYear, $dueMonth, $dueDay, 0, 0, 0, config('app.timezone'));
        $dueAt = $this->adjustToBusinessDay($baseDueAt);

        return [
            'model_code' => $model['code'],
            'model_name' => $model['name'],
            'category' => $model['category'] ?? null,
            'period_label' => $periodLabel,
            'base_due_at' => $baseDueAt,
            'due_at' => $dueAt,
            'status' => 'pending',
            'source_label' => $model['source_label'] ?? 'AEAT',
            'source_url' => $model['source_url'] ?? null,
        ];
    }

    protected function isNonBusinessDay(CarbonImmutable $date): bool
    {
        if ($date->isWeekend()) {
            return true;
        }

        $holidayKey = (string) $date->year;
        $holidays = Arr::wrap(config('aeat_calendar.business_day_holidays.'.$holidayKey, []));

        return in_array($date->toDateString(), $holidays, true);
    }
}
