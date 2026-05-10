<?php

namespace Tests\Feature;

use App\Models\AeatFiscalCalendar;
use App\Models\AeatFiscalCalendarGenerator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AeatFiscalCalendarModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_private_fiscal_calendar_module(): void
    {
        $this->get(route('aeat.fiscal-calendar.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_and_create_a_fiscal_calendar(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('aeat.fiscal-calendar.index'))
            ->assertOk()
            ->assertSee('Calendario fiscal AEAT')
            ->assertSee('Nuevo calendario');

        $response = $this->actingAs($user)->post(route('aeat.fiscal-calendar.store'), [
            'name' => 'Autónomo 2026',
            'taxpayer_nif' => '12345678Z',
            'exercise' => 2026,
            'regime' => 'mixto',
            'enabled_models' => ['303', '390'],
            'is_default' => true,
        ]);

        $calendar = AeatFiscalCalendar::query()->firstOrFail();

        $response->assertRedirect(route('aeat.fiscal-calendar.index', ['calendar' => $calendar->id]));

        $this->assertSame($user->id, $calendar->user_id);
        $this->assertSame('Autónomo 2026', $calendar->name);
        $this->assertSame('12345678Z', $calendar->taxpayer_nif);
        $this->assertCount(5, $calendar->entries);
        $this->assertDatabaseHas('aeat_fiscal_calendar_entries', [
            'aeat_fiscal_calendar_id' => $calendar->id,
            'model_code' => '303',
            'period_label' => '1T 2026',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('aeat.fiscal-calendar.index', ['calendar' => $calendar->id]))
            ->assertOk()
            ->assertSee('Próximos 30 días')
            ->assertSee('Siguiente vencimiento');

        Carbon::setTestNow();
    }

    public function test_user_can_complete_snooze_and_reopen_a_deadline(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $user = User::factory()->create();
        $calendar = $this->createCalendarForUser($user);
        $entry = $calendar->entries()->where('model_code', '303')->where('period_label', '1T 2026')->firstOrFail();

        $this->actingAs($user)
            ->post(route('aeat.fiscal-calendar.entries.complete', $entry))
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('completed', $entry->status);
        $this->assertNotNull($entry->completed_at);

        $this->actingAs($user)
            ->post(route('aeat.fiscal-calendar.entries.reopen', $entry))
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('pending', $entry->status);
        $this->assertNull($entry->completed_at);

        $this->actingAs($user)
            ->post(route('aeat.fiscal-calendar.entries.snooze', $entry))
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('snoozed', $entry->status);
        $this->assertSame('2026-04-02 12:00:00', $entry->snoozed_until?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_user_cannot_manage_another_users_deadline(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $calendar = $this->createCalendarForUser($owner);
        $entry = $calendar->entries()->where('model_code', '303')->where('period_label', '1T 2026')->firstOrFail();

        $this->actingAs($intruder)
            ->post(route('aeat.fiscal-calendar.entries.complete', $entry))
            ->assertForbidden();
    }

    protected function createCalendarForUser(User $user): AeatFiscalCalendar
    {
        $calendar = $user->aeatFiscalCalendars()->create([
            'name' => 'Calendario fiscal',
            'taxpayer_nif' => '12345678Z',
            'exercise' => 2026,
            'regime' => 'mixto',
            'enabled_models' => ['303', '390'],
            'is_default' => true,
        ]);

        app(AeatFiscalCalendarGenerator::class)->generateCalendar($calendar);

        return $calendar->refresh();
    }
}
