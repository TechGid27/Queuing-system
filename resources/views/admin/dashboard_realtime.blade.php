@push('scripts')
<script>
    const COMPLETE_URL_TEMPLATE = @json(route('admin.complete', ['id' => '__QUEUE_ID__']));
    const REJECT_URL_TEMPLATE = @json(route('admin.reject', ['id' => '__QUEUE_ID__']));
    const CALL_NEXT_URL = @json(route('admin.callNext'));

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function queueActionUrl(template, id) {
        return template.replace('__QUEUE_ID__', encodeURIComponent(String(id)));
    }

    function refreshDashboard() {
        if (!SELECTED_DEPARTMENT_ID) return;
        const waitingListUrl = new URL('{{ route("admin.waitingList") }}', window.location.origin);
        waitingListUrl.searchParams.set('department_id', SELECTED_DEPARTMENT_ID);
        const currentPage = new URL(window.location.href).searchParams.get('page');
        if (currentPage) waitingListUrl.searchParams.set('page', currentPage);

        fetch(waitingListUrl)
            .then(response => {
                if (!response.ok) throw new Error('Unable to refresh the dashboard.');
                return response.json();
            })
            .then(data => {
                // ── Stats ──────────────────────────────────────────────────
                const waitingEl   = document.getElementById('stat-waiting');
                const servingEl   = document.getElementById('stat-serving');
                const completedEl = document.getElementById('stat-completed');
                const skippedEl   = document.getElementById('stat-skipped');
                const badgeEl     = document.getElementById('waiting-badge');

                if (waitingEl)   waitingEl.innerText   = data.waiting_count;
                if (servingEl)   servingEl.innerText   = data.current ? 1 : 0;
                if (completedEl) completedEl.innerText = data.completed_count;
                if (skippedEl)   skippedEl.innerText   = data.skipped_count;
                if (badgeEl)     badgeEl.innerText     = data.waiting_count + ' in queue';

                // ── Now Serving panel ──────────────────────────────────────
                const nowServingEl = document.getElementById('now-serving-panel');
                if (nowServingEl) {
                    if (data.current) {
                        const s = data.current;
                        // served_at_ts comes from the API (Unix seconds)
                        const servedAtTs = Number(s.served_at_ts ?? Math.floor(Date.now() / 1000));
                        const ticketNumber = escapeHtml(s.ticket_number);
                        const name = escapeHtml(s.name);
                        const purpose = escapeHtml(s.purpose);
                        const phoneNumber = escapeHtml(s.phone_number);
                        const rejectUrl = escapeHtml(queueActionUrl(REJECT_URL_TEMPLATE, s.id));
                        const completeUrl = escapeHtml(queueActionUrl(COMPLETE_URL_TEMPLATE, s.id));
                        const actionsDisabled = !data.department_active ? 'disabled' : '';
                        const callNextDisabled = !data.department_active || data.queue_paused || data.waiting_count === 0 ? 'disabled' : '';
                        nowServingEl.innerHTML = `
                            <div class="text-center py-4">
                                <div class="ticket-xl text-primary mb-3">${ticketNumber}</div>
                                <div class="text-lg font-bold text-slate-800">${name}</div>
                                <div class="text-sm text-slate-400 mt-1">${purpose}</div>
                                <div class="mt-2 flex items-center justify-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                                        <i class="bi bi-phone"></i> ${phoneNumber}
                                    </span>
                                    <span id="auto-skip-timer"
                                        class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-500"
                                        data-served-at="${servedAtTs}">
                                        <i class="bi bi-clock"></i> <span id="auto-skip-label">--:--</span>
                                    </span>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-5 mt-2 flex flex-wrap gap-2 justify-center">
                                <form action="${rejectUrl}" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="department_id" value="${SELECTED_DEPARTMENT_ID}">
                                    <button type="submit" data-department-active ${actionsDisabled} class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-skip-forward-fill"></i> Skip / No Show
                                    </button>
                                </form>
                                <form action="${completeUrl}" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="department_id" value="${SELECTED_DEPARTMENT_ID}">
                                    <button type="submit" data-department-active ${actionsDisabled} class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-check-lg"></i> Complete
                                    </button>
                                </form>
                                <form action="${CALL_NEXT_URL}" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="department_id" value="${SELECTED_DEPARTMENT_ID}">
                                    <button type="submit" data-queue-running data-empty="${data.waiting_count === 0 ? '1' : '0'}" ${callNextDisabled}
                                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-arrow-right-circle-fill"></i> Call Next
                                    </button>
                                </form>
                            </div>`;
                        // Restart the countdown for the newly rendered timer
                        if (typeof updateAutoSkipTimer === 'function') updateAutoSkipTimer();
                    } else {
                        nowServingEl.innerHTML = `
                            <div class="text-center py-10">
                                <div class="text-5xl mb-3">📭</div>
                                <div class="text-base font-semibold text-slate-400">No student is currently being served</div>
                                <form action="${CALL_NEXT_URL}" method="POST" class="mt-6 inline-block">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="department_id" value="${SELECTED_DEPARTMENT_ID}">
                                    <button type="submit" data-queue-running data-empty="${data.waiting_count === 0 ? '1' : '0'}" ${!data.department_active || data.queue_paused || data.waiting_count === 0 ? 'disabled' : ''}
                                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-play-circle-fill"></i> Call First Student
                                    </button>
                                </form>
                            </div>`;
                    }
                }

                // ── Pause/Resume UI sync ──────────────────────────────────
                if (data.queue_paused !== undefined) {
                    updatePauseResumeUI(data.queue_paused, data.lunch_break_start, data.lunch_break_end, data.pause_source);
                }

                // ── Waiting list ───────────────────────────────────────────
                const listEl = document.getElementById('waiting-list-body');
                if (!listEl) return;

                if (data.waiting.length === 0) {
                    listEl.innerHTML = `
                        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                            <i class="bi bi-inbox text-3xl mb-2"></i>
                            <span class="text-sm">Queue is empty</span>
                        </div>`;
                    return;
                }

                const page = data.pagination?.current_page ?? 1;
                const pageOffset = (page - 1) * 10;
                listEl.innerHTML = data.waiting.map((s, i) => `
                    <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 ${i === 0 ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500'}">
                            ${pageOffset + i + 1}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-slate-800 truncate">${escapeHtml(s.ticket_number)}</div>
                            <div class="text-xs text-slate-400 truncate">${escapeHtml(s.name)}</div>
                        </div>
                        <span class="bg-slate-100 text-slate-500 text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 max-w-[80px] truncate">
                            ${escapeHtml(s.purpose)}
                        </span>
                    </div>`).join('');
                const paginationSummary = document.getElementById('pagination-summary');
                if (paginationSummary && data.pagination) {
                    paginationSummary.innerText = `Page ${data.pagination.current_page} of ${data.pagination.last_page}`;
                }
            })
            .catch(() => {});
    }

    if (window.Echo && SELECTED_DEPARTMENT_ID) {
        // Reuse the existing Echo instance from layout — no duplicate connection
        window.Echo.channel(`queue.${SELECTED_DEPARTMENT_ID}`).listen('.queue.updated', function(data) {
            refreshDashboard();
        });
    }

    refreshDashboard();
    setInterval(refreshDashboard, 5000);
</script>
@endpush
