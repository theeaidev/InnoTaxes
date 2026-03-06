<?php

namespace App\Http\Controllers\Aeat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aeat\StoreAeatCertificateProfileRequest;
use App\Http\Requests\Aeat\StoreAeatFiscalDataRequestRequest;
use App\Http\Requests\Aeat\SubmitAeatClaveMovilPinRequest;
use App\Models\AeatFiscalDataFile;
use App\Models\AeatFiscalDataRequest;
use App\Services\Aeat\AeatFiscalDataManager;
use App\Services\Aeat\AeatIntegrationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AeatFiscalDataController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected AeatFiscalDataManager $manager) {}

    /**
     * Display the private AEAT fiscal-data module.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $profiles = $user->aeatCertificateProfiles()->orderBy('name')->get();
        $requestPanelData = $this->requestPanelViewData($request);

        $summary = [
            'total' => $user->aeatFiscalDataRequests()->count(),
            'completed' => $user->aeatFiscalDataRequests()->where('status', 'completed')->count(),
            'pending' => $user->aeatFiscalDataRequests()->whereIn('status', ['queued', 'processing', 'retrying', 'awaiting_pin', 'preparing'])->count(),
            'failed' => $user->aeatFiscalDataRequests()->where('status', 'failed')->count(),
        ];

        $pendingPinRequests = $user->aeatFiscalDataRequests()
            ->where('status', 'awaiting_pin')
            ->latest()
            ->get();

        return view('aeat.fiscal-data.index', [
            'certificateProfiles' => $profiles,
            'summary' => $summary,
            'pendingPinRequests' => $pendingPinRequests,
            'ratificationUrls' => config('aeat.urls.ratification', []),
            ...$requestPanelData,
        ]);
    }

    /**
     * Render only the request panels so the page can refresh them without a full reload.
     */
    public function requestPanels(Request $request): View
    {
        return view('aeat.fiscal-data.partials.request-panels', $this->requestPanelViewData($request));
    }

    /**
     * Store a new secure certificate profile.
     */
    public function storeCertificateProfile(StoreAeatCertificateProfileRequest $request): RedirectResponse
    {
        $this->manager->storeCertificateProfile($request->user(), $request->validated());

        return redirect()
            ->route('aeat.fiscal-data.index')
            ->with('status', 'Certificate profile stored successfully.');
    }

    /**
     * Create a new fiscal-data request.
     */
    public function storeRequest(StoreAeatFiscalDataRequestRequest $request): RedirectResponse
    {
        try {
            $aeatRequest = $this->manager->startRequest($request->user(), $request->validated());
        } catch (Throwable $throwable) {
            return back()
                ->withInput($request->except(['reference_code']))
                ->with('error', $this->friendlyErrorMessage($throwable));
        }

        $statusMessage = $aeatRequest->isAwaitingPin()
            ? 'Cl@ve Movil challenge created. Enter the SMS PIN to continue.'
            : 'AEAT request queued successfully.';

        return redirect()
            ->route('aeat.fiscal-data.index', ['request' => $aeatRequest->getKey()])
            ->with('status', $statusMessage);
    }

    /**
     * Submit the Cl@ve Movil PIN for a pending request.
     */
    public function submitClavePin(SubmitAeatClaveMovilPinRequest $request, AeatFiscalDataRequest $aeatFiscalDataRequest): RedirectResponse
    {
        $this->authorizeRequestOwnership($request, $aeatFiscalDataRequest);
        $this->manager->submitClavePin($aeatFiscalDataRequest, $request->validated('pin'));

        $statusMessage = 'PIN accepted. The AEAT request is queued for processing.';

        return redirect()
            ->route('aeat.fiscal-data.index', ['request' => $aeatFiscalDataRequest->getKey()])
            ->with('status', $statusMessage);
    }

    /**
     * Retry a failed request when the secure state is still available.
     */
    public function retry(Request $request, AeatFiscalDataRequest $aeatFiscalDataRequest): RedirectResponse
    {
        $this->authorizeRequestOwnership($request, $aeatFiscalDataRequest);
        $this->manager->retryRequest($aeatFiscalDataRequest);

        $statusMessage = 'AEAT request queued again.';

        return redirect()
            ->route('aeat.fiscal-data.index', ['request' => $aeatFiscalDataRequest->getKey()])
            ->with('status', $statusMessage);
    }

    /**
     * Download a raw AEAT response file from the private area.
     */
    public function download(Request $request, AeatFiscalDataFile $aeatFiscalDataFile)
    {
        $this->authorizeRequestOwnership($request, $aeatFiscalDataFile->request);

        return Storage::disk($aeatFiscalDataFile->disk)->download($aeatFiscalDataFile->path, $aeatFiscalDataFile->filename);
    }

    /**
     * Ensure the authenticated user owns the AEAT request.
     */
    protected function authorizeRequestOwnership(Request $request, AeatFiscalDataRequest $aeatFiscalDataRequest): void
    {
        abort_unless($aeatFiscalDataRequest->user_id === $request->user()?->getKey(), 403);
    }

    /**
     * Build the data used by the request history and detail panels.
     *
     * @return array{
     *     requests: LengthAwarePaginator<int, AeatFiscalDataRequest>,
     *     selectedRequest: AeatFiscalDataRequest|null,
     *     selectedRequestId: int|null,
     *     recordBreakdown: Collection<int, mixed>,
     *     hasActiveRequests: bool
     * }
     */
    protected function requestPanelViewData(Request $request): array
    {
        $user = $request->user();
        $activeStatuses = ['queued', 'processing', 'retrying', 'awaiting_pin', 'preparing'];

        $requests = $user->aeatFiscalDataRequests()
            ->with([
                'files' => fn ($query) => $query->latest(),
                'errors' => fn ($query) => $query->latest()->limit(5),
            ])
            ->withCount(['records', 'errors'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $requests->withPath(route('aeat.fiscal-data.index'));

        $selectedRequestId = $request->integer('request') ?: null;
        $selectedRequest = null;

        if ($selectedRequestId) {
            $selectedRequest = $user->aeatFiscalDataRequests()
                ->with([
                    'files' => fn ($query) => $query->latest(),
                    'errors' => fn ($query) => $query->latest()->limit(10),
                ])
                ->withCount(['records', 'errors'])
                ->find($selectedRequestId);
        }

        if (! $selectedRequest) {
            $selectedRequestId = null;
        }

        $recordBreakdown = collect();

        if ($selectedRequest) {
            $recordBreakdown = $selectedRequest->records()
                ->selectRaw('record_code, count(*) as total')
                ->groupBy('record_code')
                ->orderByRaw('count(*) desc')
                ->limit(12)
                ->get();
        }

        $hasActiveRequests = $requests->getCollection()->contains(
            fn (AeatFiscalDataRequest $aeatRequest): bool => in_array($aeatRequest->status, $activeStatuses, true)
        );

        if ($selectedRequest && in_array($selectedRequest->status, $activeStatuses, true)) {
            $hasActiveRequests = true;
        }

        return [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'selectedRequestId' => $selectedRequestId,
            'recordBreakdown' => $recordBreakdown,
            'hasActiveRequests' => $hasActiveRequests,
        ];
    }

    /**
     * Map internal exceptions to user-facing feedback.
     */
    protected function friendlyErrorMessage(Throwable $throwable): string
    {
        if ($throwable instanceof AeatIntegrationException) {
            return $throwable->getMessage();
        }

        report($throwable);

        return 'The AEAT request could not be started due to an unexpected error.';
    }
}
