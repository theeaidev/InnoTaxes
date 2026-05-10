<?php

use App\Http\Controllers\Aeat\AeatFiscalDataController;
use App\Http\Controllers\ProfileController;
use App\Models\AeatFiscalCalendar;
use App\Models\AeatFiscalCalendarEntry;
use App\Models\AeatFiscalCalendarGenerator;
use App\Services\Aeat\AeatDocumentHelper;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('aeat.fiscal-calendar.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('aeat/fiscal-data')->name('aeat.fiscal-data.')->group(function () {
        Route::get('/', [AeatFiscalDataController::class, 'index'])->name('index');
        Route::get('/request-panels', [AeatFiscalDataController::class, 'requestPanels'])->name('request-panels');
        Route::post('/certificate-profiles', [AeatFiscalDataController::class, 'storeCertificateProfile'])->name('certificate-profiles.store');
        Route::post('/requests', [AeatFiscalDataController::class, 'storeRequest'])->name('requests.store');
        Route::post('/requests/{aeatFiscalDataRequest}/pin', [AeatFiscalDataController::class, 'submitClavePin'])->name('requests.pin');
        Route::post('/requests/{aeatFiscalDataRequest}/retry', [AeatFiscalDataController::class, 'retry'])->name('requests.retry');
        Route::get('/files/{aeatFiscalDataFile}', [AeatFiscalDataController::class, 'download'])->name('files.download');
    });

    Route::prefix('aeat/fiscal-calendar')->name('aeat.fiscal-calendar.')->group(function () {
        Route::get('/', function (Request $request) {
            $user = $request->user();

            $calendars = $user->aeatFiscalCalendars()
                ->withCount('entries')
                ->orderByDesc('is_default')
                ->orderByDesc('exercise')
                ->orderBy('name')
                ->get();

            $selectedCalendarId = $request->integer('calendar') ?: null;
            $selectedCalendar = null;

            if ($selectedCalendarId) {
                $selectedCalendar = $calendars->firstWhere('id', $selectedCalendarId);
            }

            if (! $selectedCalendar) {
                $selectedCalendar = $calendars->firstWhere('is_default', true)
                    ?? $calendars->first();
            }

            $entries = collect();
            $alerts = collect();

            if ($selectedCalendar instanceof AeatFiscalCalendar) {
                $selectedCalendar->load([
                    'entries' => fn ($query) => $query->orderBy('due_at'),
                ]);

                $entries = $selectedCalendar->entries;
                $alerts = $entries->filter(function (AeatFiscalCalendarEntry $entry): bool {
                    return $entry->isDueSoon() || $entry->isOverdue();
                })->sortBy('due_at')->values();
            }

            $summary = [
                'calendars' => $calendars->count(),
                'entries' => $entries->count(),
                'completed' => $entries->where('status', 'completed')->count(),
                'pending' => $entries->where('status', 'pending')->count(),
                'snoozed' => $entries->where('status', 'snoozed')->count(),
                'dueSoon' => $entries->filter(function (AeatFiscalCalendarEntry $entry): bool {
                    return $entry->isDueSoon();
                })->count(),
                'overdue' => $entries->filter(function (AeatFiscalCalendarEntry $entry): bool {
                    return $entry->isOverdue();
                })->count(),
            ];

            $nextDueEntry = $entries
                ->filter(function (AeatFiscalCalendarEntry $entry): bool {
                    return ! $entry->isCompleted();
                })
                ->sortBy('due_at')
                ->first();

            return view('aeat.fiscal-calendar.index', [
                'calendars' => $calendars,
                'selectedCalendar' => $selectedCalendar,
                'entries' => $entries,
                'summary' => $summary,
                'alerts' => $alerts,
                'nextDueEntry' => $nextDueEntry,
                'modelCatalog' => config('aeat_calendar.models', []),
                'regimeDefaults' => config('aeat_calendar.regime_defaults', []),
            ]);
        })->name('index');

        Route::post('/', function (Request $request, AeatFiscalCalendarGenerator $generator) {
            $regime = (string) $request->input('regime', 'mixto');
            $defaultModels = config("aeat_calendar.regime_defaults.$regime", config('aeat_calendar.regime_defaults.default', []));

            $request->merge([
                'name' => trim((string) $request->input('name', '')),
                'taxpayer_nif' => AeatDocumentHelper::sanitizeNif($request->input('taxpayer_nif')),
                'exercise' => (int) $request->input('exercise', now()->year),
                'regime' => $regime,
                'enabled_models' => array_values(array_unique(array_filter((array) $request->input('enabled_models', $defaultModels)))),
                'is_default' => $request->boolean('is_default'),
            ]);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'taxpayer_nif' => ['required', 'string', 'max:16'],
                'exercise' => ['required', 'integer', 'min:2024', 'max:2100'],
                'regime' => ['required', Rule::in(['autonomo', 'sociedad', 'mixto'])],
                'enabled_models' => ['required', 'array', 'min:1'],
                'enabled_models.*' => ['string', Rule::in(array_keys(config('aeat_calendar.models', [])))],
                'is_default' => ['sometimes', 'boolean'],
            ]);

            $user = $request->user();

            if (! $user->aeatFiscalCalendars()->exists()) {
                $validated['is_default'] = true;
            }

            $calendar = $user->aeatFiscalCalendars()->create([
                'name' => $validated['name'],
                'taxpayer_nif' => $validated['taxpayer_nif'],
                'exercise' => $validated['exercise'],
                'regime' => $validated['regime'],
                'enabled_models' => $validated['enabled_models'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            if ($calendar->is_default) {
                $user->aeatFiscalCalendars()
                    ->where('id', '!=', $calendar->getKey())
                    ->update(['is_default' => false]);
            }

            $generator->generateCalendar($calendar);

            return redirect()
                ->route('aeat.fiscal-calendar.index', ['calendar' => $calendar->getKey()])
                ->with('status', 'Calendario fiscal creado y vencimientos generados correctamente.');
        })->name('store');

        Route::post('/entries/{entry}/complete', function (Request $request, AeatFiscalCalendarEntry $entry) {
            abort_unless($entry->calendar?->user_id === $request->user()?->getKey(), 403);

            $entry->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'snoozed_until' => null,
            ])->save();

            return back()->with('status', 'Obligación marcada como completada.');
        })->name('entries.complete');

        Route::post('/entries/{entry}/snooze', function (Request $request, AeatFiscalCalendarEntry $entry) {
            abort_unless($entry->calendar?->user_id === $request->user()?->getKey(), 403);

            $entry->forceFill([
                'status' => 'snoozed',
                'snoozed_until' => now()->addDays(7),
                'completed_at' => null,
            ])->save();

            return back()->with('status', 'Obligación aplazada siete días.');
        })->name('entries.snooze');

        Route::post('/entries/{entry}/reopen', function (Request $request, AeatFiscalCalendarEntry $entry) {
            abort_unless($entry->calendar?->user_id === $request->user()?->getKey(), 403);

            $entry->forceFill([
                'status' => 'pending',
                'snoozed_until' => null,
                'completed_at' => null,
            ])->save();

            return back()->with('status', 'Obligación reabierta.');
        })->name('entries.reopen');
    });
});

require __DIR__.'/auth.php';
