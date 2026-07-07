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

    protected AeatFiscalCalendarGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new AeatFiscalCalendarGenerator();
    }

    // ------------------------------------------------------------------
    // adjustToBusinessDay
    // ------------------------------------------------------------------

    public function test_business_day_is_returned_unchanged_at_start_of_day(): void
    {
        $adjusted = $this->generator->adjustToBusinessDay(Carbon::parse('2026-04-20 15:30:45'));

        $this->assertInstanceOf(CarbonImmutable::class, $adjusted);
        $this->assertSame('2026-04-20 00:00:00', $adjusted->format('Y-m-d H:i:s'));
    }

    public function test_saturday_and_sunday_are_moved_to_monday(): void
    {
        $fromSaturday = $this->generator->adjustToBusinessDay(CarbonImmutable::parse('2026-06-20'));
        $fromSunday = $this->generator->adjustToBusinessDay(CarbonImmutable::parse('2026-06-21'));

        $this->assertSame('2026-06-22', $fromSaturday->toDateString());
        $this->assertSame('2026-06-22', $fromSunday->toDateString());
    }

    public function test_configured_holiday_is_skipped(): void
    {
        config()->set('aeat_calendar.business_day_holidays.2026', ['2026-01-05']);

        $adjusted = $this->generator->adjustToBusinessDay(CarbonImmutable::parse('2026-01-03'));

        $this->assertSame('2026-01-06', $adjusted->toDateString());
    }

    public function test_holiday_chain_across_a_year_boundary_is_skipped(): void
    {
        config()->set('aeat_calendar.business_day_holidays.2026', ['2026-12-31']);
        config()->set('aeat_calendar.business_day_holidays.2027', ['2027-01-01']);

        $adjusted = $this->generator->adjustToBusinessDay(CarbonImmutable::parse('2026-12-31'));

        $this->assertSame('2027-01-04', $adjusted->toDateString());
    }

    public function test_holidays_from_other_years_are_ignored(): void
    {
        config()->set('aeat_calendar.business_day_holidays.2025', ['2026-04-20']);

        $adjusted = $this->generator->adjustToBusinessDay(CarbonImmutable::parse('2026-04-20'));

        $this->assertSame('2026-04-20', $adjusted->toDateString());
    }

    // ------------------------------------------------------------------
    // defaultModelsForRegime
    // ------------------------------------------------------------------

    public function test_each_regime_resolves_its_configured_default_models(): void
    {
        $this->assertSame(
            ['303', '111', '115', '130', '131', '390', '347'],
            $this->generator->defaultModelsForRegime('autonomo')
        );
        $this->assertSame(
            ['303', '111', '115', '202', '180', '190', '390', '347'],
            $this->generator->defaultModelsForRegime('sociedad')
        );
    }

    public function test_unknown_regime_falls_back_to_default_models(): void
    {
        $this->assertSame(
            config('aeat_calendar.regime_defaults.default'),
            $this->generator->defaultModelsForRegime('inexistente')
        );
    }

    // ------------------------------------------------------------------
    // buildEntries
    // ------------------------------------------------------------------

    public function test_quarterly_model_produces_four_deadlines_on_expected_dates(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['303']]));

        $this->assertCount(4, $entries);
        $this->assertSame(
            ['1T 2026', '2T 2026', '3T 2026', '4T 2026'],
            array_column($entries, 'period_label')
        );
        $this->assertSame(
            ['2026-04-20', '2026-07-20', '2026-10-20', '2027-02-01'],
            array_map(static fn (array $entry) => $entry['due_at']->toDateString(), $entries)
        );
    }

    public function test_base_due_date_is_preserved_when_deadline_moves_to_a_business_day(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['303']]));
        $fourthQuarter = end($entries);

        // 30/01/2027 cae en sábado, así que el vencimiento efectivo pasa al lunes.
        $this->assertSame('2027-01-30', $fourthQuarter['base_due_at']->toDateString());
        $this->assertSame('2027-02-01', $fourthQuarter['due_at']->toDateString());
    }

    public function test_weekend_quarterly_deadline_moves_to_monday(): void
    {
        // En el ejercicio 2025 el 20/04 cae en domingo.
        $entries = $this->generator->buildEntries($this->makeCalendar([
            'exercise' => 2025,
            'enabled_models' => ['303'],
        ]));

        $this->assertSame('2025-04-20', $entries[0]['base_due_at']->toDateString());
        $this->assertSame('2025-04-21', $entries[0]['due_at']->toDateString());
    }

    public function test_annual_models_are_due_in_the_following_year(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['390', '347']]));

        $this->assertCount(2, $entries);
        $this->assertSame(['Ejercicio 2026', 'Ejercicio 2026'], array_column($entries, 'period_label'));

        [$resumenIva, $operacionesTerceros] = $entries;

        $this->assertSame('390', $resumenIva['model_code']);
        $this->assertSame('2027-02-01', $resumenIva['due_at']->toDateString());

        // 28/02/2027 cae en domingo.
        $this->assertSame('347', $operacionesTerceros['model_code']);
        $this->assertSame('2027-02-28', $operacionesTerceros['base_due_at']->toDateString());
        $this->assertSame('2027-03-01', $operacionesTerceros['due_at']->toDateString());
    }

    public function test_entries_are_sorted_by_due_date_across_models(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar([
            'enabled_models' => ['347', '303', '390'],
        ]));

        $timestamps = array_map(static fn (array $entry) => $entry['due_at']->getTimestamp(), $entries);
        $sorted = $timestamps;
        sort($sorted);

        $this->assertSame($sorted, $timestamps);
        $this->assertSame('1T 2026', $entries[0]['period_label']);
        $this->assertSame('347', end($entries)['model_code']);
    }

    public function test_empty_model_selection_falls_back_to_regime_defaults(): void
    {
        // autonomo: 5 modelos trimestrales (4 plazos) + 2 anuales = 22 vencimientos.
        $entries = $this->generator->buildEntries($this->makeCalendar([
            'regime' => 'autonomo',
            'enabled_models' => [],
        ]));

        $this->assertCount(22, $entries);
        $this->assertEqualsCanonicalizing(
            ['303', '111', '115', '130', '131', '390', '347'],
            array_values(array_unique(array_column($entries, 'model_code')))
        );
    }

    public function test_unknown_duplicate_and_empty_model_codes_are_discarded(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar([
            'enabled_models' => ['303', '999', '303', '', null],
        ]));

        $this->assertCount(4, $entries);
        $this->assertSame(['303'], array_values(array_unique(array_column($entries, 'model_code'))));
    }

    public function test_entry_payload_contains_the_catalog_metadata(): void
    {
        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['303']]));
        $entry = $entries[0];

        $this->assertSame('303', $entry['model_code']);
        $this->assertSame('IVA trimestral', $entry['model_name']);
        $this->assertSame('IVA', $entry['category']);
        $this->assertSame('pending', $entry['status']);
        $this->assertSame('AEAT', $entry['source_label']);
        $this->assertNull($entry['source_url']);
    }

    public function test_catalog_models_without_schedule_default_to_quarterly_day_twenty(): void
    {
        config()->set('aeat_calendar.models.T01', ['code' => 'T01', 'name' => 'Modelo de prueba']);

        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['T01']]));

        $this->assertCount(4, $entries);
        $this->assertSame('2026-04-20', $entries[0]['base_due_at']->toDateString());
        // Sin q4_day propio, el cuarto trimestre hereda el día 20 (20/01/2027, miércoles).
        $this->assertSame('2027-01-20', end($entries)['due_at']->toDateString());
        $this->assertNull($entries[0]['category']);
        $this->assertSame('AEAT', $entries[0]['source_label']);
    }

    public function test_annual_schedule_defaults_to_january_thirtieth(): void
    {
        config()->set('aeat_calendar.models.A01', [
            'code' => 'A01',
            'name' => 'Anual de prueba',
            'schedule' => ['type' => 'annual'],
        ]);

        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['A01']]));

        $this->assertCount(1, $entries);
        $this->assertSame('2027-01-30', $entries[0]['base_due_at']->toDateString());
    }

    public function test_holiday_on_a_due_date_shifts_the_generated_deadline(): void
    {
        // 20/10/2026 es martes; al marcarlo festivo el 3T debe pasar al miércoles.
        config()->set('aeat_calendar.business_day_holidays.2026', ['2026-10-20']);

        $entries = $this->generator->buildEntries($this->makeCalendar(['enabled_models' => ['303']]));

        $this->assertSame('2026-10-20', $entries[2]['base_due_at']->toDateString());
        $this->assertSame('2026-10-21', $entries[2]['due_at']->toDateString());
    }

    // ------------------------------------------------------------------
    // generateCalendar (persistencia)
    // ------------------------------------------------------------------

    public function test_generate_calendar_persists_entries_and_stamps_generation_time(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $calendar = $this->createPersistedCalendar(['enabled_models' => ['303', '390']]);

        $this->generator->generateCalendar($calendar);
        $calendar->refresh();

        $this->assertCount(5, $calendar->entries);
        $this->assertSame('2026-03-26 12:00:00', $calendar->last_generated_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('aeat_fiscal_calendar_entries', [
            'aeat_fiscal_calendar_id' => $calendar->id,
            'model_code' => '390',
            'period_label' => 'Ejercicio 2026',
            'status' => 'pending',
        ]);

        Carbon::setTestNow();
    }

    public function test_regenerating_replaces_previous_entries_without_duplicates(): void
    {
        $calendar = $this->createPersistedCalendar(['enabled_models' => ['303', '390']]);

        $this->generator->generateCalendar($calendar);
        $originalIds = $calendar->entries()->pluck('id')->all();

        $calendar->update(['enabled_models' => ['111']]);
        $this->generator->generateCalendar($calendar->refresh());

        $entries = $calendar->refresh()->entries;

        $this->assertCount(4, $entries);
        $this->assertSame(['111'], $entries->pluck('model_code')->unique()->values()->all());
        $this->assertEmpty(array_intersect($originalIds, $entries->pluck('id')->all()));
    }

    // ------------------------------------------------------------------
    // Riesgo de las entradas generadas
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function makeCalendar(array $attributes = []): AeatFiscalCalendar
    {
        return new AeatFiscalCalendar(array_merge([
            'name' => 'Calendario de prueba',
            'taxpayer_nif' => '12345678Z',
            'exercise' => 2026,
            'regime' => 'mixto',
            'enabled_models' => ['303'],
        ], $attributes));
    }

    protected function createPersistedCalendar(array $attributes = []): AeatFiscalCalendar
    {
        $user = User::factory()->create();

        return $user->aeatFiscalCalendars()->create(array_merge([
            'name' => 'Calendario de prueba',
            'taxpayer_nif' => '12345678Z',
            'exercise' => 2026,
            'regime' => 'mixto',
            'enabled_models' => ['303'],
            'is_default' => true,
        ], $attributes));
    }
}
