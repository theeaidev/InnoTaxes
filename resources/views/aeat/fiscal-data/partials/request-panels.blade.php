<div class="row g-4 align-items-start" data-aeat-history-content data-has-active-requests="{{ $hasActiveRequests ? '1' : '0' }}" data-selected-request-id="{{ $selectedRequestId ?? '' }}">
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
                                    $isSelected = (int) $selectedRequestId === $aeatRequest->id;
                                    $viewParams = ['request' => $aeatRequest->id];

                                    if ($requests->currentPage() > 1) {
                                        $viewParams['page'] = $requests->currentPage();
                                    }
                                @endphp
                                <tr @class(['table-active' => $isSelected])>
                                    <td>
                                        <div class="fw-semibold">#{{ $aeatRequest->id }} · {{ $aeatRequest->taxpayer_nif }}</div>
                                        <div class="text-muted small">{{ $aeatRequest->created_at?->format('d/m/Y H:i') }} · pdp={{ $aeatRequest->pdp ? 'S' : 'N' }}</div>
                                        @if ($aeatRequest->auth_nif)
                                            <div class="text-muted small aeat-break-anywhere">Authenticated as {{ $aeatRequest->auth_nif }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $aeatRequest->auth_method) }}</div>
                                        <div class="aeat-request-live-meta">
                                            <span class="aeat-request-live-pill aeat-request-live-pill--neutral">Attempt {{ $aeatRequest->attempts }}</span>
                                            <span class="aeat-request-live-pill aeat-request-live-pill--neutral">Queued {{ $aeatRequest->queued_at?->format('H:i:s') ?: 'n/a' }}</span>
                                            @if ($aeatRequest->processing_at)
                                                <span class="aeat-request-live-pill aeat-request-live-pill--neutral">Processing {{ $aeatRequest->processing_at->format('H:i:s') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge text-bg-{{ $statusClass }}">{{ str_replace('_', ' ', $aeatRequest->status) }}</span>
                                            <span class="badge text-bg-{{ $domicileClass }}">{{ str_replace('_', ' ', $aeatRequest->domicile_status) }}</span>
                                        </div>
                                        <div class="text-muted small mt-2">Stage: {{ $aeatRequest->stage ?: 'n/a' }}</div>
                                        @if ($aeatRequest->last_error_message)
                                            <div class="text-danger small mt-1 aeat-break-anywhere">{{ $aeatRequest->last_error_message }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small text-muted">Files: {{ $aeatRequest->files->count() }}</div>
                                        <div class="small text-muted">Records: {{ $aeatRequest->records_count }}</div>
                                        <div class="small text-muted">Errors: {{ $aeatRequest->errors_count }}</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap aeat-request-actions">
                                            <a
                                                href="{{ route('aeat.fiscal-data.index', $viewParams) }}#request-detail-panel"
                                                class="btn btn-sm aeat-request-view-btn {{ $isSelected ? 'aeat-request-view-btn--active' : 'aeat-request-view-btn--idle' }}"
                                                data-aeat-request-select
                                                data-request-id="{{ $aeatRequest->id }}"
                                            >
                                                {{ $isSelected ? 'Viewing' : 'View' }}
                                            </a>
                                            @if ($latestFile)
                                                <a href="{{ route('aeat.fiscal-data.files.download', $latestFile) }}" class="btn btn-sm btn-outline-secondary">Raw file</a>
                                            @endif
                                            @if ($aeatRequest->canRetry())
                                                <form method="POST" action="{{ route('aeat.fiscal-data.requests.retry', $aeatRequest) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm aeat-request-retry-btn">Retry</button>
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

    <div class="col-xl-4 request-detail-column" id="request-detail-panel">
        @if ($selectedRequest)
            @php
                $selectedLivePillClass = match ($selectedRequest->status) {
                    'queued' => 'aeat-request-live-pill--queued',
                    'processing' => 'aeat-request-live-pill--processing',
                    'retrying', 'preparing' => 'aeat-request-live-pill--retrying',
                    'awaiting_pin' => 'aeat-request-live-pill--awaiting',
                    'completed' => 'aeat-request-live-pill--done',
                    'failed' => 'aeat-request-live-pill--failed',
                    default => 'aeat-request-live-pill--neutral',
                };
                $selectedDomicilePillClass = match ($selectedRequest->domicile_status) {
                    'ratified' => 'aeat-request-live-pill--done',
                    'not_ratified' => 'aeat-request-live-pill--failed',
                    default => 'aeat-request-live-pill--neutral',
                };
            @endphp
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pb-0">
                    <h3 class="h5 mb-1">Request Detail</h3>
                    <p class="text-muted small mb-0">Request #{{ $selectedRequest->id }} for {{ $selectedRequest->taxpayer_nif }}</p>
                </div>
                <div class="card-body">
                    <div class="aeat-request-signal">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="aeat-request-live-pill {{ $selectedLivePillClass }}">{{ str_replace('_', ' ', $selectedRequest->status) }}</span>
                            <span class="aeat-request-live-pill {{ $selectedDomicilePillClass }}">{{ str_replace('_', ' ', $selectedRequest->domicile_status) }}</span>
                            <span class="aeat-request-live-pill aeat-request-live-pill--neutral">Attempt {{ $selectedRequest->attempts }}</span>
                            <span class="aeat-request-live-pill aeat-request-live-pill--neutral">Stage {{ $selectedRequest->stage ?: 'n/a' }}</span>
                        </div>
                        <div class="aeat-request-signal__grid">
                            <div class="aeat-request-signal__item">
                                <div class="aeat-request-signal__label">Queued</div>
                                <div class="aeat-request-signal__value aeat-break-anywhere">{{ $selectedRequest->queued_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</div>
                            </div>
                            <div class="aeat-request-signal__item">
                                <div class="aeat-request-signal__label">Processing</div>
                                <div class="aeat-request-signal__value aeat-break-anywhere">{{ $selectedRequest->processing_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($selectedRequest->last_error_message)
                        <div class="alert alert-danger small shadow-sm mb-3 aeat-break-anywhere">
                            <div class="fw-semibold mb-1">Latest AEAT failure</div>
                            <div>{{ $selectedRequest->last_error_message }}</div>
                        </div>
                    @endif

                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Auth method</dt>
                        <dd class="col-7 text-capitalize">{{ str_replace('_', ' ', $selectedRequest->auth_method) }}</dd>
                        <dt class="col-5 text-muted">pdp</dt>
                        <dd class="col-7">{{ $selectedRequest->pdp ? 'S' : 'N' }}</dd>
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7">{{ str_replace('_', ' ', $selectedRequest->status) }}</dd>
                        <dt class="col-5 text-muted">Stage</dt>
                        <dd class="col-7">{{ str_replace('_', ' ', $selectedRequest->stage) }}</dd>
                        <dt class="col-5 text-muted">Attempts</dt>
                        <dd class="col-7">{{ $selectedRequest->attempts }}</dd>
                        <dt class="col-5 text-muted">Domicile</dt>
                        <dd class="col-7">{{ str_replace('_', ' ', $selectedRequest->domicile_status) }}</dd>
                        <dt class="col-5 text-muted">Queued at</dt>
                        <dd class="col-7">{{ $selectedRequest->queued_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</dd>
                        <dt class="col-5 text-muted">Processing at</dt>
                        <dd class="col-7">{{ $selectedRequest->processing_at?->format('d/m/Y H:i:s') ?: 'n/a' }}</dd>
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
                                    <div class="aeat-file-card__content">
                                        <div class="fw-semibold aeat-break-anywhere">{{ $file->filename }}</div>
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
                            <div class="border rounded-4 p-3 aeat-error-card">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-3 mb-2">
                                    <div class="aeat-error-card__content">
                                        <div class="fw-semibold aeat-break-anywhere">{{ $error->message }}</div>
                                        <div class="text-muted small aeat-break-anywhere">{{ $error->occurred_at?->format('d/m/Y H:i:s') ?: 'n/a' }} · stage {{ $error->stage }} · attempt {{ $error->attempt }}</div>
                                    </div>
                                    <div class="aeat-error-card__meta text-sm-end">
                                        @if ($error->code)
                                            <div class="badge text-bg-danger mb-1">{{ $error->code }}</div>
                                        @endif
                                        <div class="small text-muted">Retryable: <span class="fw-semibold">{{ $error->retryable ? 'yes' : 'no' }}</span></div>
                                    </div>
                                </div>
                                @if ($error->details)
                                    <pre class="small bg-light border rounded-3 p-3 mb-0 aeat-error-card__details">{{ json_encode($error->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
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
