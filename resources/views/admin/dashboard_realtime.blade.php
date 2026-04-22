@push('scripts')
<script>
    function refreshDashboard() {
        fetch('{{ route("admin.waitingList") }}')
            .then(r => r.json())
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
                        nowServingEl.innerHTML = `
                            <div class="text-center py-4">
                                <div class="ticket-xl text-primary mb-3">${s.ticket_number}</div>
                                <div class="text-lg font-bold text-slate-800">${s.name}</div>
                                <div class="text-sm text-slate-400 mt-1">${s.purpose}</div>
                                <div class="mt-2">
                                    <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                                        <i class="bi bi-phone"></i> ${s.phone_number ?? ''}
                                    </span>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-5 mt-2 flex flex-wrap gap-2 justify-center">
                                <form action="/admin/reject/${s.id}" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                                        <i class="bi bi-skip-forward-fill"></i> Skip / No Show
                                    </button>
                                </form>
                                <form action="/admin/accept/${s.id}" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                                        <i class="bi bi-check-lg"></i> Complete
                                    </button>
                                </form>
                                <form action="/admin/call-next" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" ${data.waiting_count == 0 ? 'disabled' : ''}
                                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-arrow-right-circle-fill"></i> Call Next
                                    </button>
                                </form>
                            </div>`;
                    } else {
                        nowServingEl.innerHTML = `
                            <div class="text-center py-10">
                                <div class="text-5xl mb-3">📭</div>
                                <div class="text-base font-semibold text-slate-400">No student is currently being served</div>
                                <form action="/admin/call-next" method="POST" class="mt-6 inline-block">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" ${data.waiting_count == 0 ? 'disabled' : ''}
                                        class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="bi bi-play-circle-fill"></i> Call First Student
                                    </button>
                                </form>
                            </div>`;
                    }
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

                listEl.innerHTML = data.waiting.map((s, i) => `
                    <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 ${i === 0 ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500'}">
                            ${i + 1}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-slate-800 truncate">${s.ticket_number}</div>
                            <div class="text-xs text-slate-400 truncate">${s.name}</div>
                        </div>
                        <span class="bg-slate-100 text-slate-500 text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 max-w-[80px] truncate">
                            ${s.purpose}
                        </span>
                    </div>`).join('');
            });
    }

    if (window.PUSHER_APP_KEY) {
        // Reuse the existing Echo instance from layout — no duplicate connection
        window.Echo.channel('queue').listen('.queue.updated', function(data) {
            refreshDashboard();
        });
    } else {
        setInterval(refreshDashboard, 5000);
    }
</script>
@endpush
