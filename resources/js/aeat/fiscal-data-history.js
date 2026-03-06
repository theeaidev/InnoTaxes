function formatSyncLabel(date) {
    const formattedTime = new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);

    return `Synced ${formattedTime}`;
}

export default function registerAeatRequestHistory(Alpine) {
    Alpine.data('aeatRequestHistory', (config = {}) => ({
        endpoint: config.endpoint ?? '',
        pollInterval: Number(config.pollInterval ?? 5000),
        selectedRequestId: config.selectedRequestId ?? null,
        hasActiveRequests: false,
        isRefreshing: false,
        lastSyncedLabel: 'Waiting for live updates',
        pollHandle: null,
        abortController: null,
        handleClickBound: null,
        handlePopStateBound: null,

        init() {
            if (!this.endpoint || !this.$refs.content) {
                return;
            }

            this.handleClickBound = this.handleClick.bind(this);
            this.handlePopStateBound = this.handlePopState.bind(this);

            this.$el.addEventListener('click', this.handleClickBound);
            window.addEventListener('popstate', this.handlePopStateBound);

            this.syncPanelState();
            this.lastSyncedLabel = formatSyncLabel(new Date());
            this.startPolling();
        },

        destroy() {
            this.stopPolling();
            this.abortOngoingRequest();

            if (this.handleClickBound) {
                this.$el.removeEventListener('click', this.handleClickBound);
            }

            if (this.handlePopStateBound) {
                window.removeEventListener('popstate', this.handlePopStateBound);
            }
        },

        startPolling() {
            this.stopPolling();

            this.pollHandle = window.setInterval(() => {
                if (document.visibilityState !== 'visible' || this.isRefreshing || !this.hasActiveRequests) {
                    return;
                }

                this.refreshPanels();
            }, this.pollInterval);
        },

        stopPolling() {
            if (!this.pollHandle) {
                return;
            }

            window.clearInterval(this.pollHandle);
            this.pollHandle = null;
        },

        async handleClick(event) {
            const requestLink = event.target.closest('[data-aeat-request-select]');

            if (requestLink && this.$refs.content.contains(requestLink)) {
                event.preventDefault();

                await this.refreshPanels({
                    sourceHref: requestLink.href,
                    selectedRequestId: Number(requestLink.dataset.requestId || this.selectedRequestId),
                    updateHistory: true,
                    scrollToDetail: true,
                });

                return;
            }

            const paginationLink = event.target.closest('.pagination a[href]');

            if (paginationLink && this.$refs.content.contains(paginationLink)) {
                event.preventDefault();

                await this.refreshPanels({
                    sourceHref: paginationLink.href,
                    updateHistory: true,
                });
            }
        },

        async handlePopState() {
            await this.refreshPanels({
                sourceHref: window.location.href,
                updateHistory: false,
            });
        },

        buildPanelUrl(sourceHref, selectedRequestId = this.selectedRequestId) {
            const sourceUrl = new URL(sourceHref ?? window.location.href, window.location.origin);
            const panelUrl = new URL(this.endpoint, window.location.origin);

            sourceUrl.searchParams.forEach((value, key) => {
                panelUrl.searchParams.set(key, value);
            });

            if (selectedRequestId) {
                panelUrl.searchParams.set('request', String(selectedRequestId));
            } else {
                panelUrl.searchParams.delete('request');
            }

            return panelUrl;
        },

        buildPageUrl(sourceHref, selectedRequestId = this.selectedRequestId) {
            const pageUrl = new URL(sourceHref ?? window.location.href, window.location.origin);

            if (selectedRequestId) {
                pageUrl.searchParams.set('request', String(selectedRequestId));
                pageUrl.hash = 'request-detail-panel';
            } else {
                pageUrl.searchParams.delete('request');
                pageUrl.hash = '';
            }

            return pageUrl;
        },

        syncPanelState() {
            const panelRoot = this.$refs.content.querySelector('[data-aeat-history-content]');

            if (!panelRoot) {
                this.hasActiveRequests = false;
                return;
            }

            this.hasActiveRequests = panelRoot.dataset.hasActiveRequests === '1';

            const selectedRequestId = panelRoot.dataset.selectedRequestId;
            this.selectedRequestId = selectedRequestId ? Number(selectedRequestId) : null;
        },

        abortOngoingRequest() {
            if (!this.abortController) {
                return;
            }

            this.abortController.abort();
            this.abortController = null;
        },

        async refreshPanels({
            sourceHref = window.location.href,
            selectedRequestId = this.selectedRequestId,
            updateHistory = false,
            scrollToDetail = false,
        } = {}) {
            if (this.isRefreshing) {
                return;
            }

            this.abortOngoingRequest();
            this.abortController = new AbortController();
            this.isRefreshing = true;

            const panelUrl = this.buildPanelUrl(sourceHref, selectedRequestId);
            const pageUrl = this.buildPageUrl(sourceHref, selectedRequestId);

            try {
                const response = await fetch(panelUrl, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: this.abortController.signal,
                });

                if (!response.ok) {
                    throw new Error(`Unexpected response status: ${response.status}`);
                }

                this.$refs.content.innerHTML = await response.text();
                this.syncPanelState();
                this.lastSyncedLabel = formatSyncLabel(new Date());

                if (updateHistory) {
                    window.history.replaceState({}, '', pageUrl);
                }

                if (scrollToDetail && this.selectedRequestId) {
                    document.getElementById('request-detail-panel')?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('AEAT request panels could not be refreshed.', error);
                    this.lastSyncedLabel = 'Live update failed';
                }
            } finally {
                this.isRefreshing = false;
                this.abortController = null;
            }
        },
    }));
}
