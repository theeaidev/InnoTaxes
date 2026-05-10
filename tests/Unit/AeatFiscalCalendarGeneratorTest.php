<?php

namespace Tests\Unit;

use App\Models\AeatFiscalCalendar;
use App\Models\AeatFiscalCalendarEntry;
use App\Models\AeatFiscalCalendarGenerator;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AeatFiscalCalendarGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_adjusts_deadlines_to_next_business_day(): void
    {
        $generator = new AeatFiscalCalendarGenerator();

        config()->set('aeat_calendar.business_day_holidays.2026', ['2026-01-05']);

        $adjusted = $generator->adjustToBusinessDay(CarbonImmutable::parse('2026-01-03'));

        $this->assertSame('2026-01-06', $adjusted->toDateString());
    }

    public function test_generator_builds_quarterly_and_annual_entries(): void
    {
        $user = User::factory()->create();
        $calendar = $user->aeatFiscalCalendars()->create([
            'name' => 'Autónomo 2026',
            'taxpayer_nif' => '12345678Z',
            'exercise' => 2026,
            'regime' => 'mixto',
            'enabled_models' => ['303', '390'],
            'is_default' => true,
        ]);

        $generator = new AeatFiscalCalendarGenerator();
        $entries = $generator->buildEntries($calendar);

        $this->assertCount(5, $entries);
        $this->assertSame(['303', '303', '303', '303', '390'], array_values(array_column($entries, 'model_code')));
        $this->assertSame(['1T 2026', '2T 2026', '3T 2026', '4T 2026', 'Ejercicio 2026'], array_values(array_column($entries, 'period_label')));
    }

    public function test_risk_state_is_computed_from_deadline_and_status(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $soon = new AeatFiscalCalendarEntry([
            'due_at' => CarbonImmutable::parse('2026-04-10'),
            'status' => 'pending',
        ]);

        $overdue = new AeatFiscalCalendarEntry([
            'due_at' => CarbonImmutable::parse('2026-03-20'),
            'status' => 'pending',
        ]);

        $completed = new AeatFiscalCalendarEntry([
            'due_at' => CarbonImmutable::parse('2026-03-20'),
            'status' => 'completed',
            'completed_at' => CarbonImmutable::parse('2026-03-21'),
        ]);

        $this->assertSame('warning', $soon->riskState());
        $this->assertSame('danger', $overdue->riskState());
        $this->assertSame('success', $completed->riskState());
        $this->assertSame('Próximo', $soon->riskLabel());
        $this->assertSame('Vencido', $overdue->riskLabel());
        $this->assertSame('Completado', $completed->riskLabel());

        Carbon::setTestNow();
    }
}
