<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column gap-1">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">{{ __('AEAT Fiscal Data') }}</h2>
            <p class="text-sm text-gray-500 mb-0">Private module for AEAT Renta {{ config('aeat.exercise') }} downloads inside the authenticated area.</p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="alert alert-success shadow-sm mb-0">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger shadow-sm mb-0">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-warning shadow-sm mb-0">
                    <div class="fw-semibold mb-2">Please review the highlighted fields.</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $aeatReleaseDate = config('aeat.release_date')
                    ? \Illuminate\Support\Carbon::parse(config('aeat.release_date'))->startOfDay()
                    : null;
            @endphp
            @if ($aeatReleaseDate && now()->lt($aeatReleaseDate))
                <div class="alert alert-warning shadow-sm mb-0">
                    AEAT has not opened fiscal-data downloads for exercise {{ config('aeat.exercise') }} yet. According to the official calendar, access starts on {{ $aeatReleaseDate->format('d/m/Y') }}. Ratification checks can still be recorded before that date.
                </div>
            @endif

            @once
                <style>
                    .aeat-guide {
                        overflow: hidden;
                        border: 1px solid rgba(24, 63, 71, 0.1);
                        border-radius: 1.5rem;
                        background: #fff;
                    }

                    .aeat-guide__summary {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 1rem;
                        padding: 1.1rem 1.25rem;
                        list-style: none;
                        cursor: pointer;
                        background: linear-gradient(135deg, rgba(88, 199, 194, 0.22), rgba(235, 168, 77, 0.16));
                    }

                    .aeat-guide__summary::-webkit-details-marker {
                        display: none;
                    }

                    .aeat-guide__title {
                        color: var(--brand-ink);
                        font-weight: 700;
                        font-size: 1.05rem;
                    }

                    .aeat-guide__chevron {
                        width: 0.85rem;
                        height: 0.85rem;
                        border-right: 2px solid #4b4db7;
                        border-bottom: 2px solid #4b4db7;
                        transform: rotate(45deg);
                        transition: transform 0.2s ease;
                    }

                    .aeat-guide[open] .aeat-guide__chevron {
                        transform: rotate(225deg);
                    }

                    .aeat-guide__body {
                        padding: 1.25rem;
                        color: var(--brand-ink);
                        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 251, 251, 0.98)), #fff;
                    }

                    .aeat-guide__lead {
                        max-width: 72rem;
                        margin-bottom: 1.25rem;
                        color: var(--brand-ink);
                        font-size: 0.98rem;
                        line-height: 1.65;
                    }

                    .aeat-guide__panel {
                        height: 100%;
                        padding: 1rem;
                        border: 1px solid rgba(24, 63, 71, 0.1);
                        border-radius: 1.25rem;
                        background: rgba(255, 255, 255, 0.92);
                        box-shadow: 0 10px 24px rgba(24, 63, 71, 0.05);
                    }

                    .aeat-guide__panel h3 {
                        color: var(--brand-ink);
                    }

                    .aeat-guide__panel p {
                        color: var(--brand-slate);
                        line-height: 1.6;
                    }

                    .aeat-guide__badge {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 2.4rem;
                        height: 2.4rem;
                        margin-bottom: 0.85rem;
                        padding: 0 0.7rem;
                        border-radius: 999px;
                        background: linear-gradient(135deg, var(--brand-ink), #295861);
                        color: #fff;
                        font-size: 0.78rem;
                        font-weight: 700;
                        letter-spacing: 0.08em;
                    }

                    .aeat-guide__footer {
                        margin-top: 1.25rem;
                        padding: 1rem 1.1rem;
                        border: 1px solid rgba(88, 199, 194, 0.18);
                        border-radius: 1.25rem;
                        background: linear-gradient(135deg, rgba(88, 199, 194, 0.14), rgba(255, 255, 255, 0.95));
                        color: var(--brand-slate);
                        line-height: 1.65;
                    }

                    .aeat-guide__label {
                        display: inline-block;
                        margin-bottom: 0.5rem;
                        color: var(--brand-ink);
                        font-size: 0.78rem;
                        font-weight: 700;
                        letter-spacing: 0.14em;
                        text-transform: uppercase;
                    }

                    .request-detail-column {
                        scroll-margin-top: 2rem;
                    }

                    .aeat-request-actions {
                        align-items: center;
                    }

                    .aeat-request-view-btn {
                        min-width: 6.4rem;
                        padding: 0.68rem 1.15rem;
                        border-radius: 999px;
                        font-weight: 700;
                        letter-spacing: 0.01em;
                        transition:
                            transform 0.2s ease,
                            box-shadow 0.2s ease,
                            background 0.2s ease,
                            border-color 0.2s ease,
                            color 0.2s ease;
                    }

                    .aeat-request-view-btn:hover,
                    .aeat-request-view-btn:focus {
                        transform: translateY(-1px);
                    }

                    .aeat-request-view-btn--idle {
                        color: var(--brand-ink);
                        border-color: rgba(24, 63, 71, 0.12);
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(240, 248, 247, 0.96));
                        box-shadow: 0 10px 22px rgba(24, 63, 71, 0.08);
                    }

                    .aeat-request-view-btn--idle:hover,
                    .aeat-request-view-btn--idle:focus {
                        color: var(--brand-ink);
                        border-color: rgba(88, 199, 194, 0.45);
                        background: linear-gradient(135deg, rgba(217, 244, 241, 0.9), rgba(255, 255, 255, 0.98));
                        box-shadow: 0 14px 28px rgba(24, 63, 71, 0.1);
                    }

                    .aeat-request-view-btn--active {
                        color: #fff;
                        border-color: transparent;
                        background: linear-gradient(135deg, var(--brand-teal), #2ea8a1);
                        box-shadow: 0 16px 30px rgba(88, 199, 194, 0.28);
                    }

                    .aeat-request-view-btn--active:hover,
                    .aeat-request-view-btn--active:focus {
                        color: #fff;
                        border-color: transparent;
                        background: linear-gradient(135deg, #47b8b3, var(--brand-ink));
                        box-shadow: 0 18px 34px rgba(24, 63, 71, 0.2);
                    }

                    .aeat-request-retry-btn {
                        min-width: 5.75rem;
                        padding: 0.58rem 1rem;
                        border-radius: 999px;
                        font-weight: 700;
                        letter-spacing: 0.01em;
                        color: #c0392b;
                        border-color: rgba(231, 76, 60, 0.34);
                        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(255, 237, 235, 0.96));
                        box-shadow: 0 10px 22px rgba(192, 57, 43, 0.08);
                        transition:
                            transform 0.2s ease,
                            box-shadow 0.2s ease,
                            background 0.2s ease,
                            border-color 0.2s ease,
                            color 0.2s ease;
                    }

                    .aeat-request-retry-btn:hover,
                    .aeat-request-retry-btn:focus {
                        transform: translateY(-1px);
                        color: #fff;
                        border-color: transparent;
                        background: linear-gradient(135deg, #ef6b5a, #c0392b);
                        box-shadow: 0 16px 30px rgba(192, 57, 43, 0.22);
                    }

                    .aeat-request-live-meta {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.45rem;
                        margin-top: 0.75rem;
                    }

                    .aeat-request-live-pill {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.35rem;
                        padding: 0.32rem 0.7rem;
                        border-radius: 999px;
                        font-size: 0.76rem;
                        font-weight: 700;
                        letter-spacing: 0.03em;
                        line-height: 1;
                        white-space: nowrap;
                    }

                    .aeat-request-live-pill--neutral {
                        color: var(--brand-ink);
                        background: rgba(24, 63, 71, 0.08);
                    }

                    .aeat-request-live-pill--queued {
                        color: #fff;
                        background: linear-gradient(135deg, #5b6cf9, #4355dc);
                        box-shadow: 0 10px 18px rgba(67, 85, 220, 0.18);
                    }

                    .aeat-request-live-pill--processing {
                        color: #fff;
                        background: linear-gradient(135deg, #20a4a8, #167d88);
                        box-shadow: 0 10px 18px rgba(22, 125, 136, 0.18);
                    }

                    .aeat-request-live-pill--retrying {
                        color: #6b4d00;
                        background: linear-gradient(135deg, rgba(255, 214, 102, 0.96), rgba(255, 239, 196, 0.94));
                        box-shadow: 0 10px 18px rgba(235, 168, 77, 0.18);
                    }

                    .aeat-request-live-pill--awaiting {
                        color: #7a4a00;
                        background: linear-gradient(135deg, rgba(255, 212, 128, 0.96), rgba(255, 244, 214, 0.95));
                    }

                    .aeat-request-live-pill--done {
                        color: #0f6b4e;
                        background: linear-gradient(135deg, rgba(180, 244, 214, 0.96), rgba(240, 255, 248, 0.95));
                    }

                    .aeat-request-live-pill--failed {
                        color: #a1281d;
                        background: linear-gradient(135deg, rgba(255, 204, 199, 0.98), rgba(255, 244, 243, 0.95));
                    }

                    .aeat-request-signal {
                        display: grid;
                        gap: 0.9rem;
                        margin-bottom: 1.15rem;
                        padding: 1rem 1.05rem;
                        border: 1px solid rgba(24, 63, 71, 0.1);
                        border-radius: 1.25rem;
                        background: linear-gradient(135deg, rgba(88, 199, 194, 0.12), rgba(255, 255, 255, 0.96));
                    }

                    .aeat-request-signal__grid {
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 0.75rem;
                    }

                    .aeat-request-signal__item {
                        min-width: 0;
                        padding: 0.7rem 0.8rem;
                        border-radius: 1rem;
                        background: rgba(255, 255, 255, 0.8);
                        border: 1px solid rgba(24, 63, 71, 0.08);
                    }

                    .aeat-request-signal__label {
                        margin-bottom: 0.25rem;
                        color: var(--brand-slate);
                        font-size: 0.72rem;
                        font-weight: 700;
                        letter-spacing: 0.12em;
                        text-transform: uppercase;
                    }

                    .aeat-request-signal__value {
                        color: var(--brand-ink);
                        font-size: 1rem;
                        font-weight: 700;
                    }

                    .aeat-error-card {
                        overflow: hidden;
                        background: rgba(255, 255, 255, 0.88);
                    }

                    .aeat-error-card__content {
                        flex: 1 1 auto;
                        min-width: 0;
                    }

                    .aeat-error-card__meta {
                        flex: 0 0 auto;
                        min-width: 5.5rem;
                    }

                    .aeat-error-card__details {
                        max-width: 100%;
                        overflow: auto;
                    }

                    .aeat-break-anywhere {
                        overflow-wrap: anywhere;
                        word-break: break-word;
                    }

                    @media (max-width: 575.98px) {
                        .aeat-request-view-btn,
                        .aeat-request-retry-btn {
                            min-width: 5.5rem;
                            padding-inline: 1rem;
                        }

                        .aeat-error-card__meta {
                            min-width: 0;
                        }
                    }

                    @media (max-width: 767.98px) {
                        .aeat-request-signal__grid {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>
            @endonce

            <details class="aeat-guide shadow-sm">
                <summary class="aeat-guide__summary">
                    <span class="aeat-guide__title">How this AEAT Fiscal Data module works</span>
                    <span class="aeat-guide__chevron" aria-hidden="true"></span>
                </summary>

                <div class="aeat-guide__body">
                    <p class="aeat-guide__lead">This private module lets an authenticated user create traceable AEAT requests inside the app without replacing the normal app login. It stores request history, raw files, normalized records, errors, retries, and the status of each attempt in one place.</p>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <article class="aeat-guide__panel">
                                <div class="aeat-guide__badge">01</div>
                                <h3 class="h6 mb-2">Supported access methods</h3>
                                <p class="small mb-0">The workflow is prepared to request AEAT fiscal data with a certificate profile, Cl@ve PIN / Cl@ve Movil, or an AEAT reference number, always using the documented AEAT flows and the selected pdp value.</p>
                            </article>
                        </div>
                        <div class="col-lg-4">
                            <article class="aeat-guide__panel">
                                <div class="aeat-guide__badge">02</div>
                                <h3 class="h6 mb-2">What the module validates now</h3>
                                <p class="small mb-0">Before downloading, the module checks whether the taxpayer has the fiscal domicile ratified when the documented AEAT flow allows that pre-check. That result is saved in the request history so you can see if the domicile is already ratified or still pending.</p>
                            </article>
                        </div>
                        <div class="col-lg-4">
                            <article class="aeat-guide__panel">
                                <div class="aeat-guide__badge">03</div>
                                <h3 class="h6 mb-2">What changes on 19 March</h3>
                                <p class="small mb-0">Until {{ $aeatReleaseDate?->format('d/m/Y') ?? 'the official AEAT opening date' }}, this module can record requests and domicile-ratification checks, but AEAT has not opened the fiscal-data download service yet. Once AEAT opens the campaign, the same requests can continue into the actual fiscal-data download stage.</p>
                            </article>
                        </div>
                    </div>

                    <div class="aeat-guide__footer">
                        <span class="aeat-guide__label">Summary</span>
                        <p class="small mb-0">The module is designed to support certificate, Cl@ve PIN, and reference-based access, while preserving a secure audit trail. Right now it already validates domicile ratification and request traceability, and from 19 March onward it is expected to proceed with the AEAT fiscal-data download when the external service becomes available.</p>
                    </div>
                </div>
            </details>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase text-muted small fw-semibold">Requests</div><div class="display-6 fw-bold text-dark">{{ $summary['total'] }}</div><div class="text-muted small">Complete history stored in the private area.</div></div></div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase text-muted small fw-semibold">Completed</div><div class="display-6 fw-bold text-success">{{ $summary['completed'] }}</div><div class="text-muted small">Raw and normalized AEAT files available.</div></div></div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase text-muted small fw-semibold">Pending</div><div class="display-6 fw-bold text-primary">{{ $summary['pending'] }}</div><div class="text-muted small">Queued, processing, or waiting for a Cl@ve PIN.</div></div></div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase text-muted small fw-semibold">Failed</div><div class="display-6 fw-bold text-danger">{{ $summary['failed'] }}</div><div class="text-muted small">Structured errors and retry metadata are preserved.</div></div></div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-xl-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pb-0">
                            <h3 class="h5 mb-1">Secure Certificate Profiles</h3>
                            <p class="text-muted small mb-0">Stored encrypted at rest and reused only inside the authenticated area.</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('aeat.fiscal-data.certificate-profiles.store') }}" enctype="multipart/form-data" class="row g-3">
                                @csrf
                                <div class="col-12"><label class="form-label">Profile name</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="AEAT collaborator certificate"></div>
                                <div class="col-md-4"><label class="form-label">Format</label><select name="certificate_format" class="form-select">@foreach (['p12', 'pfx', 'pem'] as $format)<option value="{{ $format }}" @selected(old('certificate_format', 'p12') === $format)>{{ strtoupper($format) }}</option>@endforeach</select></div>
                                <div class="col-md-8"><label class="form-label">Passphrase</label><input type="password" name="passphrase" class="form-control" placeholder="Optional if your certificate requires it"></div>
                                <div class="col-12"><label class="form-label">Certificate file</label><input type="file" name="certificate_file" class="form-control"></div>
                                <div class="col-12"><label class="form-label">Private key file</label><input type="file" name="private_key_file" class="form-control"><div class="form-text">Only required when the certificate is provided separately from the private key.</div></div>
                                <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-brand">Save profile</button></div>
                            </form>

                            <hr class="my-4" style="border-color: rgba(24, 63, 71, 0.12);">

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Name</th><th>Format</th><th>Last used</th></tr></thead>
                                    <tbody>
                                        @forelse ($certificateProfiles as $profile)
                                            <tr>
                                                <td><div class="fw-semibold">{{ $profile->name }}</div><div class="text-muted small">{{ $profile->certificate_original_name }}</div></td>
                                                <td class="text-uppercase">{{ $profile->certificate_format }}</td>
                                                <td class="text-muted small">{{ optional($profile->last_used_at)->diffForHumans() ?? 'Never' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted small">No certificate profiles yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7">
                    <div class="card border-0 shadow-sm" x-data="{ method: '{{ old('auth_method', 'certificate') }}' }">
                        <div class="card-header bg-white border-0 pb-0">
                            <h3 class="h5 mb-1">New AEAT Request</h3>
                            <p class="text-muted small mb-0">The current auth flow stays untouched. This module runs entirely after login.</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('aeat.fiscal-data.requests.store') }}" class="row g-3">
                                @csrf
                                <div class="col-md-6"><label class="form-label">Taxpayer NIF</label><input type="text" name="taxpayer_nif" class="form-control" value="{{ old('taxpayer_nif') }}" placeholder="12345678Z"></div>
                                <div class="col-md-3"><label class="form-label">pdp</label><select name="pdp" class="form-select"><option value="S" @selected(old('pdp', 'S') === 'S')>S</option><option value="N" @selected(old('pdp') === 'N')>N</option></select><div class="form-text">S = fiscal + personal, N = fiscal only.</div></div>
                                <div class="col-md-3"><label class="form-label">Auth method</label><select name="auth_method" class="form-select" x-model="method"><option value="certificate">Certificate</option><option value="reference">Reference</option><option value="clave_movil">Cl@ve Movil</option></select></div>
                                <div class="col-md-6"><label class="form-label">Pre-check certificate (optional)</label><select name="precheck_certificate_profile_id" class="form-select"><option value="">Skip explicit ratification pre-check</option>@foreach ($certificateProfiles as $profile)<option value="{{ $profile->id }}" @selected((string) old('precheck_certificate_profile_id') === (string) $profile->id)>{{ $profile->name }}</option>@endforeach</select><div class="form-text">Uses the documented ratification endpoint before download when available.</div></div>
                                <div class="col-md-6" x-show="method === 'certificate'" x-cloak><label class="form-label">Certificate profile</label><select name="certificate_profile_id" class="form-select"><option value="">Select profile</option>@foreach ($certificateProfiles as $profile)<option value="{{ $profile->id }}" @selected((string) old('certificate_profile_id') === (string) $profile->id)>{{ $profile->name }}</option>@endforeach</select></div>
                                <div class="col-md-6" x-show="method === 'reference' || method === 'clave_movil'" x-cloak><label class="form-label">Authentication NIF</label><input type="text" name="auth_nif" class="form-control" value="{{ old('auth_nif') }}" placeholder="NIF of presenter or authenticated person"></div>
                                <div class="col-md-6" x-show="method === 'reference'" x-cloak><label class="form-label">AEAT reference code</label><input type="text" name="reference_code" class="form-control" value="{{ old('reference_code') }}" placeholder="6 characters"><div class="form-text">Stored only as encrypted state for retries.</div></div>
                                <div class="col-md-6" x-show="method === 'clave_movil'" x-cloak><label class="form-label">Document date (DNI)</label><input type="text" name="fecha" class="form-control" value="{{ old('fecha') }}" placeholder="DD-MM-AAAA or DD/MM/AAAA"></div>
                                <div class="col-md-6" x-show="method === 'clave_movil'" x-cloak><label class="form-label">Support number (NIE)</label><input type="text" name="soporte" class="form-control" value="{{ old('soporte') }}" placeholder="Support or residence document number"></div>
                                <div class="col-12"><div class="alert alert-light border mb-0"><div class="fw-semibold mb-1">Documented constraints respected</div><ul class="small mb-0 ps-3"><li>Credential issuance stays outside this product, as required by the AEAT PDF.</li><li>Ratification itself opens the official AEAT application; this module does not scrape or automate it.</li><li>Only documented endpoints, parameters, cookies, and flows are used.</li></ul></div></div>
                                <div class="col-12 d-flex justify-content-between align-items-center gap-3 flex-wrap"><div class="small text-muted">Ratification links: <a href="{{ $ratificationUrls['certificate'] ?? '#' }}" target="_blank" rel="noreferrer">certificate</a>, <a href="{{ $ratificationUrls['reference'] ?? '#' }}" target="_blank" rel="noreferrer">reference</a>, <a href="{{ $ratificationUrls['clave_movil'] ?? '#' }}" target="_blank" rel="noreferrer">Cl@ve Movil</a>.</div><button type="submit" class="btn btn-brand">Queue request</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if ($pendingPinRequests->isNotEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="h5 mb-1">Pending Cl@ve Movil PINs</h3>
                        <p class="text-muted small mb-0">These requests already passed the first challenge step and are waiting for the SMS PIN.</p>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($pendingPinRequests as $pendingRequest)
                                <div class="col-lg-6">
                                    <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <div>
                                                <div class="fw-semibold">{{ $pendingRequest->taxpayer_nif }}</div>
                                                <div class="text-muted small">Request #{{ $pendingRequest->id }} · {{ $pendingRequest->created_at?->diffForHumans() }}</div>
                                            </div>
                                            <span class="badge text-bg-warning">Awaiting PIN</span>
                                        </div>
                                        <div class="small text-muted mb-3">
                                            Mobile: {{ data_get($pendingRequest->session_state, 'masked_mobile', 'AEAT did not return a masked mobile number.') }}
                                        </div>
                                        <form method="POST" action="{{ route('aeat.fiscal-data.requests.pin', $pendingRequest) }}" class="row g-2">
                                            @csrf
                                            <div class="col-sm-7">
                                                <label class="form-label mb-1">SMS PIN</label>
                                                <input type="text" name="pin" class="form-control" placeholder="6 digits" inputmode="numeric">
                                            </div>
                                            <div class="col-sm-5 d-flex align-items-end">
                                                <button type="submit" class="btn btn-brand w-100">Submit PIN</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div
                x-data="aeatRequestHistory({
                    endpoint: @js(route('aeat.fiscal-data.request-panels')),
                    selectedRequestId: @js($selectedRequestId),
                    pollInterval: 5000,
                })"
                x-init="init()"
                class="d-flex flex-column gap-3"
            >
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <p class="small text-muted mb-0">The history and detail panels refresh automatically so status, domicile checks, and errors stay in sync while AEAT requests are moving.</p>
                    <div class="d-inline-flex align-items-center gap-2 small">
                        <span class="badge rounded-pill border" :class="hasActiveRequests ? 'text-bg-success border-success-subtle' : 'text-bg-light text-muted'">
                            <span x-show="isRefreshing" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            <span x-text="hasActiveRequests ? (isRefreshing ? 'Refreshing' : 'Auto-refresh on') : 'Auto-refresh paused'"></span>
                        </span>
                        <span class="text-muted" x-text="lastSyncedLabel"></span>
                    </div>
                </div>

                <div x-ref="content">
                    @include('aeat.fiscal-data.partials.request-panels')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>




