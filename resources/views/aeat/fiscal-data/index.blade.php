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

            @if (app()->isLocal() && config('queue.default') !== 'sync')
                <div class="alert alert-info shadow-sm mb-0">
                    This environment processes AEAT requests in the background. After clicking <span class="fw-semibold">Queue request</span>, run <code>php artisan queue:work</code> to process pending jobs.
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

                            <hr class="my-4">

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

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 pb-0">
                            <h3 class="h5 mb-1">Request History</h3>
                            <p class="text-muted small mb-0">Every request keeps status, errors, retry attempts, and downloadable raw files in the private area.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Request</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Artifacts</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($requests as $aeatRequest)
                                            @php
                                                $latestFile = $aeatRequest->files->first();
                                                $statusClass = match ($aeatRequest->status) {
                                                    'completed' => 'success',
                                                    'failed' => 'danger',
                                                    'awaiting_pin' => 'warning',
                                                    'processing', 'queued', 'retrying', 'preparing' => 'primary',
                                                    default => 'secondary',
                                                };
                                                $domicileClass = match ($aeatRequest->domicile_status) {
                                                    'ratified' => 'success',
                                                    'not_ratified' => 'danger',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <tr @class(['table-active' => (int) request('request') === $aeatRequest->id])>
                                                <td>
                                                    <div class="fw-semibold">#{{ $aeatRequest->id }} · {{ $aeatRequest->taxpayer_nif }}</div>
                                                    <div class="text-muted small">{{ $aeatRequest->created_at?->format('d/m/Y H:i') }} · pdp={{ $aeatRequest->pdp ? 'S' : 'N' }}</div>
                                                    @if ($aeatRequest->auth_nif)
                                                        <div class="text-muted small">Authenticated as {{ $aeatRequest->auth_nif }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $aeatRequest->auth_method) }}</div>
                                                    <div class="text-muted small">Attempts: {{ $aeatRequest->attempts }}</div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="badge text-bg-{{ $statusClass }}">{{ str_replace('_', ' ', $aeatRequest->status) }}</span>
                                                        <span class="badge text-bg-{{ $domicileClass }}">{{ str_replace('_', ' ', $aeatRequest->domicile_status) }}</span>
                                                    </div>
                                                    <div class="text-muted small mt-2">Stage: {{ $aeatRequest->stage ?: 'n/a' }}</div>
                                                    @if ($aeatRequest->last_error_message)
                                                        <div class="text-danger small mt-1">{{ $aeatRequest->last_error_message }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="small text-muted">Files: {{ $aeatRequest->files->count() }}</div>
                                                    <div class="small text-muted">Records: {{ $aeatRequest->records_count }}</div>
                                                    <div class="small text-muted">Errors: {{ $aeatRequest->errors_count }}</div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                        <a href="{{ route('aeat.fiscal-data.index', ['request' => $aeatRequest->id]) }}" class="btn btn-sm btn-outline-brand">View</a>
                                                        @if ($latestFile)
                                                            <a href="{{ route('aeat.fiscal-data.files.download', $latestFile) }}" class="btn btn-sm btn-outline-secondary">Raw file</a>
                                                        @endif
                                                        @if ($aeatRequest->canRetry())
                                                            <form method="POST" action="{{ route('aeat.fiscal-data.requests.retry', $aeatRequest) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Retry</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-muted small">No AEAT requests have been created yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($requests->hasPages())
                                <div class="mt-4">{{ $requests->links() }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    @if ($selectedRequest)
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0 pb-0">
                                <h3 class="h5 mb-1">Request Detail</h3>
                                <p class="text-muted small mb-0">Request #{{ $selectedRequest->id }} for {{ $selectedRequest->taxpayer_nif }}</p>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0 small">
                                    <dt class="col-5 text-muted">Auth method</dt>
                                    <dd class="col-7 text-capitalize">{{ str_replace('_', ' ', $selectedRequest->auth_method) }}</dd>
                                    <dt class="col-5 text-muted">pdp</dt>
                                    <dd class="col-7">{{ $selectedRequest->pdp ? 'S' : 'N' }}</dd>
                                    <dt class="col-5 text-muted">Status</dt>
                                    <dd class="col-7">{{ str_replace('_', ' ', $selectedRequest->status) }}</dd>
                                    <dt class="col-5 text-muted">Stage</dt>
                                    <dd class="col-7">{{ $selectedRequest->stage ?: 'n/a' }}</dd>
                                    <dt class="col-5 text-muted">Domicile</dt>
                                    <dd class="col-7">{{ str_replace('_', ' ', $selectedRequest->domicile_status) }}</dd>
                                    <dt class="col-5 text-muted">Queued at</dt>
                                    <dd class="col-7">{{ $selectedRequest->queued_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</dd>
                                    <dt class="col-5 text-muted">Downloaded at</dt>
                                    <dd class="col-7">{{ $selectedRequest->downloaded_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</dd>
                                    <dt class="col-5 text-muted">Completed at</dt>
                                    <dd class="col-7">{{ $selectedRequest->completed_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</dd>
                                </dl>

                                @if ($selectedRequest->payload)
                                    <hr>
                                    <div class="fw-semibold mb-2">Safe request payload</div>
                                    <pre class="small bg-light border rounded-3 p-3 mb-0">{{ json_encode($selectedRequest->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0 pb-0">
                                <h3 class="h5 mb-1">Files and Records</h3>
                                <p class="text-muted small mb-0">Raw AEAT files stay private; parsed records are normalized and queryable.</p>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-3">
                                    @forelse ($selectedRequest->files as $file)
                                        <div class="border rounded-4 p-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $file->filename }}</div>
                                                    <div class="text-muted small">{{ number_format($file->bytes) }} bytes · {{ $file->line_count }} lines · {{ $file->record_count }} records</div>
                                                </div>
                                                <a href="{{ route('aeat.fiscal-data.files.download', $file) }}" class="btn btn-sm btn-outline-secondary">Download</a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted small">No raw AEAT file has been stored for this request yet.</div>
                                    @endforelse
                                </div>

                                @if ($recordBreakdown->isNotEmpty())
                                    <hr>
                                    <div class="fw-semibold mb-2">Top record codes</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0 align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Record code</th>
                                                    <th class="text-end">Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($recordBreakdown as $recordRow)
                                                    <tr>
                                                        <td>{{ $recordRow->record_code ?: 'n/a' }}</td>
                                                        <td class="text-end">{{ $recordRow->total }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pb-0">
                                <h3 class="h5 mb-1">Errors and Retry Trail</h3>
                                <p class="text-muted small mb-0">Structured AEAT failures are preserved per request for support and auditing.</p>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-3">
                                    @forelse ($selectedRequest->errors as $error)
                                        <div class="border rounded-4 p-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                <div>
                                                    <div class="fw-semibold">{{ $error->message }}</div>
                                                    <div class="text-muted small">{{ $error->occurred_at?->format('d/m/Y H:i:s') ?: 'n/a' }} · stage {{ $error->stage }} · attempt {{ $error->attempt }}</div>
                                                </div>
                                                <div class="text-end">
                                                    @if ($error->code)
                                                        <div class="badge text-bg-danger mb-1">{{ $error->code }}</div>
                                                    @endif
                                                    <div class="small text-muted">Retryable: {{ $error->retryable ? 'yes' : 'no' }}</div>
                                                </div>
                                            </div>
                                            @if ($error->details)
                                                <pre class="small bg-light border rounded-3 p-3 mb-0">{{ json_encode($error->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-muted small">No errors recorded for this request.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h3 class="h5">Select a request</h3>
                                <p class="text-muted mb-0">Use the history table to inspect stored files, parsed record counts, and structured errors for a specific AEAT request.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>



