<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column gap-1">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">{{ __('Calendario fiscal AEAT') }}</h2>
            <p class="text-sm text-gray-500 mb-0">
                {{ __('Vencimientos, alertas y semáforo de riesgo para modelos AEAT recurrentes.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectedCalendarId = $selectedCalendar?->id;
        $formRegime = old('regime', 'mixto');
        $checkedModels = old('enabled_models', $regimeDefaults[$formRegime] ?? $regimeDefaults['default'] ?? []);
        $groupedModels = collect($modelCatalog)->groupBy('category');
        $calendarOptions = $calendars->mapWithKeys(fn ($calendar) => [$calendar->id => $calendar->name.' · '.$calendar->taxpayer_nif.' · '.$calendar->exercise]);
    @endphp

    @once
        <style>
            .fiscal-calendar-shell {
                position: relative;
            }

            .calendar-glow {
                position: absolute;
                inset: -2rem auto auto -4rem;
                width: 12rem;
                height: 12rem;
                border-radius: 999px;
                background: radial-gradient(circle, rgba(88, 199, 194, 0.22), transparent 68%);
                pointer-events: none;
                filter: blur(6px);
                z-index: 0;
            }

            .calendar-card {
                position: relative;
                z-index: 1;
                border: 1px solid rgba(24, 63, 71, 0.08);
                border-radius: 1.35rem;
                box-shadow: 0 16px 40px rgba(24, 63, 71, 0.08);
            }

            .calendar-soft-panel {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(240, 248, 247, 0.94));
            }

            .calendar-meta {
                color: #52737a;
                font-size: 0.9rem;
            }

            .calendar-stat {
                min-height: 100%;
                border-radius: 1.25rem;
                border: 1px solid rgba(24, 63, 71, 0.08);
                background: rgba(255, 255, 255, 0.96);
                box-shadow: 0 12px 28px rgba(24, 63, 71, 0.06);
            }

            .calendar-stat__value {
                font-size: 2rem;
                font-weight: 800;
                line-height: 1;
                color: var(--brand-ink, #183f47);
            }

            .calendar-legend {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .calendar-legend__item {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.35rem 0.8rem;
                border-radius: 999px;
                background: rgba(24, 63, 71, 0.06);
                color: #183f47;
                font-size: 0.78rem;
                font-weight: 700;
            }

            .calendar-dot {
                width: 0.65rem;
                height: 0.65rem;
                border-radius: 999px;
                display: inline-block;
            }

            .calendar-dot--success { background: #198754; }
            .calendar-dot--warning { background: #f0ad4e; }
            .calendar-dot--danger { background: #dc3545; }
            .calendar-dot--info { background: #0dcaf0; }
            .calendar-dot--secondary { background: #6c757d; }

            .calendar-deadline-row td {
                vertical-align: top;
            }

            .calendar-period {
                min-width: 8rem;
            }

            .calendar-action-form {
                display: inline-block;
            }

            .calendar-badge {
                border-radius: 999px;
                font-weight: 700;
                letter-spacing: 0.01em;
            }

            .calendar-model-group + .calendar-model-group {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px dashed rgba(24, 63, 71, 0.12);
            }
        </style>
    @endonce

    <div class="py-12 fiscal-calendar-shell">
        <div class="calendar-glow"></div>
        <div class="container-fluid px-4 px-lg-5 position-relative">
            @if (session('status'))
                <div class="alert alert-success shadow-sm mb-4">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger shadow-sm mb-4">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-warning shadow-sm mb-4">
                    <div class="fw-semibold mb-2">{{ __('Revisa los campos marcados.') }}</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card calendar-card calendar-soft-panel h-100">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                                <div>
                                    <p class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Calendario activo') }}</p>
                                    @if ($selectedCalendar)
                                        <h3 class="h4 mb-1">{{ $selectedCalendar->name }}</h3>
                                        <div class="calendar-meta">
                                            {{ $selectedCalendar->taxpayer_nif }} · {{ ucfirst($selectedCalendar->regime) }} · {{ __('Ejercicio') }} {{ $selectedCalendar->exercise }}
                                            @if ($selectedCalendar->is_default)
                                                · <span class="badge text-bg-primary calendar-badge">{{ __('Predeterminado') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <h3 class="h4 mb-1">{{ __('Todavía no hay un calendario fiscal') }}</h3>
                                        <div class="calendar-meta">{{ __('Crea uno para empezar a ver vencimientos y alertas.') }}</div>
                                    @endif
                                </div>

                                @if ($calendars->isNotEmpty())
                                    <form method="GET" action="{{ route('aeat.fiscal-calendar.index') }}" class="min-w-0">
                                        <label class="form-label fw-semibold text-muted small mb-1" for="calendar-select">{{ __('Cambiar calendario') }}</label>
                                        <div class="input-group">
                                            <select id="calendar-select" name="calendar" class="form-select">
                                                @foreach ($calendars as $calendar)
                                                    <option value="{{ $calendar->id }}" @selected((string) $selectedCalendarId === (string) $calendar->id)>{{ $calendarOptions[$calendar->id] }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-primary" type="submit">{{ __('Abrir') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </div>

                            <div class="calendar-legend mb-4">
                                <span class="calendar-legend__item"><span class="calendar-dot calendar-dot--success"></span>{{ __('En plazo') }}</span>
                                <span class="calendar-legend__item"><span class="calendar-dot calendar-dot--warning"></span>{{ __('Próximo / aplazado') }}</span>
                                <span class="calendar-legend__item"><span class="calendar-dot calendar-dot--danger"></span>{{ __('Vencido') }}</span>
                                <span class="calendar-legend__item"><span class="calendar-dot calendar-dot--info"></span>{{ __('Completado / estado') }}</span>
                            </div>

                            @if ($selectedCalendar)
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="calendar-stat p-3">
                                            <div class="text-uppercase text-muted small fw-semibold mb-2">{{ __('Total') }}</div>
                                            <div class="calendar-stat__value">{{ $summary['entries'] }}</div>
                                            <div class="text-muted small">{{ __('Obligaciones generadas') }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="calendar-stat p-3">
                                            <div class="text-uppercase text-muted small fw-semibold mb-2">{{ __('Próximos 30 días') }}</div>
                                            <div class="calendar-stat__value text-warning">{{ $summary['dueSoon'] }}</div>
                                            <div class="text-muted small">{{ __('Requieren atención') }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="calendar-stat p-3">
                                            <div class="text-uppercase text-muted small fw-semibold mb-2">{{ __('Vencidos') }}</div>
                                            <div class="calendar-stat__value text-danger">{{ $summary['overdue'] }}</div>
                                            <div class="text-muted small">{{ __('Semáforo en rojo') }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="calendar-stat p-3">
                                            <div class="text-uppercase text-muted small fw-semibold mb-2">{{ __('Completados') }}</div>
                                            <div class="calendar-stat__value text-success">{{ $summary['completed'] }}</div>
                                            <div class="text-muted small">{{ __('Ya cerrados') }}</div>
                                        </div>
                                    </div>
                                </div>

                                @if ($nextDueEntry)
                                    <div class="alert alert-info mt-4 mb-0 shadow-sm">
                                        <div class="fw-semibold mb-1">{{ __('Siguiente vencimiento') }}</div>
                                        <div>
                                            #{{ $nextDueEntry->model_code }} {{ $nextDueEntry->model_name }} · {{ $nextDueEntry->period_label }} · {{ $nextDueEntry->due_at->format('d/m/Y') }} · {{ $nextDueEntry->dueCaption() }}
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-secondary mb-0">
                                    {{ __('Crea tu primer calendario para empezar a ver los vencimientos recurrentes de AEAT.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card calendar-card calendar-soft-panel h-100">
                        <div class="card-body">
                            <p class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Nuevo calendario') }}</p>
                            <h3 class="h5 mb-2">{{ __('Preparar ejercicio fiscal') }}</h3>
                            <p class="text-muted small mb-4">
                                {{ __('Crea un calendario por NIF y ejercicio. El sistema generará los modelos recurrentes y ajustará las fechas que caigan en fin de semana o festivo configurado.') }}
                            </p>

                            <form method="POST" action="{{ route('aeat.fiscal-calendar.store') }}" class="row g-3">
                                @csrf
                                <div class="col-12">
                                    <label for="calendar-name" class="form-label">{{ __('Nombre del calendario') }}</label>
                                    <input id="calendar-name" type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="{{ __('Autónomo 2026') }}" required>
                                </div>

                                <div class="col-md-7">
                                    <label for="taxpayer-nif" class="form-label">{{ __('NIF del contribuyente') }}</label>
                                    <input id="taxpayer-nif" type="text" name="taxpayer_nif" value="{{ old('taxpayer_nif') }}" class="form-control" placeholder="12345678Z" required>
                                </div>

                                <div class="col-md-5">
                                    <label for="calendar-exercise" class="form-label">{{ __('Ejercicio') }}</label>
                                    <input id="calendar-exercise" type="number" name="exercise" value="{{ old('exercise', now()->year) }}" class="form-control" min="2024" max="2100" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="calendar-regime" class="form-label">{{ __('Régimen') }}</label>
                                    <select id="calendar-regime" name="regime" class="form-select">
                                        <option value="mixto" @selected($formRegime === 'mixto')>{{ __('Mixto / general') }}</option>
                                        <option value="autonomo" @selected($formRegime === 'autonomo')>{{ __('Autónomo') }}</option>
                                        <option value="sociedad" @selected($formRegime === 'sociedad')>{{ __('Sociedad') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch mb-0">
                                        <input id="calendar-default" class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', ! $calendars->count()))>
                                        <label class="form-check-label" for="calendar-default">{{ __('Establecer como predeterminado') }}</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="calendar-model-group">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="fw-semibold">{{ __('Modelos sugeridos') }}</div>
                                            <div class="text-muted small">{{ __('Puedes desmarcar los que no apliquen') }}</div>
                                        </div>

                                        @foreach ($groupedModels as $category => $models)
                                            <div class="calendar-model-group">
                                                <div class="text-uppercase text-muted small fw-semibold mb-2">{{ $category ?: __('Otros') }}</div>
                                                <div class="row g-2">
                                                    @foreach ($models as $model)
                                                        <div class="col-12 col-md-6">
                                                            <div class="form-check">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    name="enabled_models[]"
                                                                    value="{{ $model['code'] }}"
                                                                    id="model-{{ $model['code'] }}"
                                                                    @checked(in_array($model['code'], (array) $checkedModels, true))
                                                                >
                                                                <label class="form-check-label" for="model-{{ $model['code'] }}">
                                                                    <span class="fw-semibold">{{ $model['code'] }}</span>
                                                                    <span class="text-muted">· {{ $model['name'] }}</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary">{{ __('Crear calendario') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if ($selectedCalendar)
                <div class="row g-4 mt-1">
                    <div class="col-xl-4">
                        <div class="card calendar-card h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Alertas activas') }}</p>
                                <h3 class="h5 mb-3">{{ __('Pendientes que necesitan atención') }}</h3>

                                @if ($alerts->isNotEmpty())
                                    <div class="vstack gap-3">
                                        @foreach ($alerts->take(6) as $alert)
                                            <div class="border rounded-4 p-3">
                                                <div class="d-flex justify-content-between gap-3">
                                                    <div>
                                                        <div class="fw-semibold">#{{ $alert->model_code }} {{ $alert->model_name }}</div>
                                                        <div class="text-muted small">{{ $alert->period_label }}</div>
                                                    </div>
                                                    <span class="badge text-bg-{{ $alert->riskBadgeClass() }} calendar-badge">{{ $alert->riskLabel() }}</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    <span class="badge text-bg-{{ $alert->statusBadgeClass() }} calendar-badge">{{ $alert->statusLabel() }}</span>
                                                    <span class="badge text-bg-light text-dark border calendar-badge">{{ $alert->dueCaption() }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-success mb-0">{{ __('No hay alertas activas para este calendario.') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card calendar-card h-100">
                            <div class="card-header bg-white border-0 pb-0">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                    <div>
                                        <h3 class="h5 mb-1">{{ __('Vencimientos del calendario') }}</h3>
                                        <p class="text-muted small mb-0">{{ __('Cada fila muestra el estado, el riesgo y las acciones rápidas.') }}</p>
                                    </div>
                                    <div class="text-muted small align-self-lg-end">{{ __('Base:') }} {{ $selectedCalendar->taxpayer_nif }} · {{ __('Ejercicio') }} {{ $selectedCalendar->exercise }}</div>
                                </div>
                            </div>
                            <div class="card-body pt-3">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Modelo') }}</th>
                                                <th class="calendar-period">{{ __('Periodo') }}</th>
                                                <th>{{ __('Fecha límite') }}</th>
                                                <th>{{ __('Riesgo') }}</th>
                                                <th>{{ __('Estado') }}</th>
                                                <th class="text-end">{{ __('Acciones') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($entries as $entry)
                                                <tr class="calendar-deadline-row">
                                                    <td>
                                                        <div class="fw-semibold">#{{ $entry->model_code }} {{ $entry->model_name }}</div>
                                                        <div class="text-muted small">{{ $entry->category ?: __('Sin categoría') }}</div>
                                                    </td>
                                                    <td class="calendar-period">
                                                        <div class="fw-semibold">{{ $entry->period_label }}</div>
                                                        <div class="text-muted small">{{ $entry->source_label ?: 'AEAT' }}</div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold">{{ $entry->due_at->format('d/m/Y') }}</div>
                                                        <div class="text-muted small">{{ $entry->dueCaption() }}</div>
                                                        @if ($entry->wasAdjusted())
                                                            <div class="text-muted small">{{ __('Ajustado desde') }} {{ $entry->base_due_at->format('d/m/Y') }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-{{ $entry->riskBadgeClass() }} calendar-badge">{{ $entry->riskLabel() }}</span>
                                                        @if ($entry->isDueSoon())
                                                            <div class="text-muted small mt-1">{{ __('Requiere seguimiento') }}</div>
                                                        @elseif ($entry->isOverdue())
                                                            <div class="text-danger small mt-1">{{ __('Está fuera de plazo') }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-{{ $entry->statusBadgeClass() }} calendar-badge">{{ $entry->statusLabel() }}</span>
                                                        @if ($entry->isSnoozed() && $entry->snoozed_until)
                                                            <div class="text-muted small mt-1">{{ __('Hasta') }} {{ $entry->snoozed_until->format('d/m/Y') }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                            @if (! $entry->isCompleted())
                                                                <form method="POST" action="{{ route('aeat.fiscal-calendar.entries.complete', $entry) }}" class="calendar-action-form">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-success">{{ __('Completado') }}</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('aeat.fiscal-calendar.entries.snooze', $entry) }}" class="calendar-action-form">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('Aplazar 7 días') }}</button>
                                                                </form>
                                                            @else
                                                                <form method="POST" action="{{ route('aeat.fiscal-calendar.entries.reopen', $entry) }}" class="calendar-action-form">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Reabrir') }}</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
