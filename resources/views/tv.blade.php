<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>ACLC Queue Display</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#dbeafe' },
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        window.PUSHER_APP_KEY     = "{{ config('broadcasting.connections.pusher.key') }}";
        window.PUSHER_APP_CLUSTER = "{{ config('broadcasting.connections.pusher.options.cluster') }}";
    </script>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; }
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #fff; }

        .ticket-now {
            font-size: 75px;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
        }
        .ticket-next {
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1;
        }
        .label-tag {
            font-size: clamp(0.65rem, 1.2vw, 1rem);
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }
        .live-dot { animation: pulse-dot 1.8s infinite; }

        @keyframes flash-in {
            0%   { opacity: 0; transform: scale(0.85); }
            60%  { opacity: 1; transform: scale(1.04); }
            100% { transform: scale(1); }
        }
        .flash { animation: flash-in .5s ease forwards; }

        @keyframes ticker {
            0%   { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .ticker-inner { animation: ticker 28s linear infinite; white-space: nowrap; }

        .queue-row { transition: background .3s; }
        .queue-row:nth-child(odd) { background: rgba(255,255,255,.04); }
    </style>
</head>
<body class="flex flex-col" style="height:100vh;">

    {{-- ── TOP BAR ── --}}
    <header class="flex items-center justify-between px-10 py-4 border-b border-white/10 shrink-0"
            style="background: rgba(255, 255, 255, 0.04);">
        <div class="flex items-center gap-4">
            <img src="/newAclcLogo-BQdiVkLw-removebg-preview.png" alt="ACLC Logo" class="w-12 h-12 object-contain shrink-0 rounded-full bg-white">
            <div>
                <div class="text-white font-black text-xl tracking-tight leading-none">ACLC Mandaue</div>
                <div class="text-slate-400 text-xs font-medium mt-0.5">Cashier's Office — Virtual Queue</div>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2 bg-green-500/10 border border-green-500/30 px-4 py-1.5 rounded-full">
                <span class="w-2 h-2 bg-green-400 rounded-full live-dot"></span>
                <span class="text-green-400 text-xs font-bold tracking-widest">LIVE</span>
            </div>
            <div class="text-right">
                <div class="text-white font-bold text-lg tabular-nums" id="tv-clock">--:--</div>
                <div class="text-slate-500 text-xs" id="tv-date">--</div>
            </div>
        </div>
    </header>

    {{-- ── MAIN CONTENT ── --}}
    <div class="flex flex-1 min-h-0 gap-0">

        {{-- LEFT: Now Serving + Next --}}
        <div class="flex flex-col flex-1 min-w-0 border-r border-white/10">

            {{-- NOW SERVING --}}
            <div id="tv-now-serving-bg" class="flex-1 flex flex-col items-center justify-center px-10 py-5 relative"
                 style="background: {{ $queuePaused ? 'linear-gradient(135deg, #78350f 0%, #92400e 50%, #b45309 100%)' : 'linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%)' }};">

                <div id="tv-break-banner" class="{{ $queuePaused ? '' : 'hidden' }} absolute top-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-yellow-400/20 border border-yellow-400/40 px-5 py-2 rounded-full">
                    <i class="bi bi-pause-circle-fill text-yellow-300"></i>
                    <span class="text-yellow-200 text-sm font-bold tracking-wide">QUEUE ON BREAK — Please wait</span>
                </div>

                <div class="label-tag text-blue-300 mb-6">Now Serving</div>
                <div class="ticket-now text-white text-center" id="tv-current">{{ $currentNumber }}</div>
                @if($currentServing)
                <div class="mt-6 text-center">
                    <div class="text-blue-200 text-xl font-semibold " id="tv-current-name">{{ $currentServing->name }}

                    </div>
                    <div class="inline-flex items-center gap-2 mt-2 bg-white/10 px-4 py-1.5 rounded-full">
                        <i class="bi bi-tag-fill text-blue-300 text-sm"></i>
                        <span class="text-blue-100 text-sm font-medium" id="tv-current-purpose">{{ $currentServing->purpose }}</span>
                    </div>
                </div>
                @else
                <div class="mt-6 text-blue-300 text-lg font-medium" id="tv-current-name"></div>
                <div class="mt-2 hidden" id="tv-current-purpose-wrap">
                    <div class="inline-flex items-center gap-2 bg-white/10 px-4 py-1.5 rounded-full">
                        <i class="bi bi-tag-fill text-blue-300 text-sm"></i>
                        <span class="text-blue-100 text-sm font-medium" id="tv-current-purpose"></span>
                    </div>
                </div>
                @endif
                <div class="mt-8 flex items-center gap-2 bg-white/10 px-5 py-2 rounded-full">
                    <i class="bi bi-bell-fill text-yellow-300 text-sm"></i>
                    <span class="text-white/80 text-sm font-medium">Please proceed to the window</span>
                </div>
            </div>

            {{-- NEXT IN LINE --}}
            <div class="shrink-0 flex items-center justify-between px-10 py-6 border-t border-white/10"
                 style="background: rgba(255,255,255,.03);">
                <div>
                    <div class="label-tag text-slate-400 mb-2">Next in Line</div>
                    <div class="ticket-next text-slate-300" id="tv-next">{{ $nextNumber }}</div>
                </div>
                <div class="text-right">
                    <div class="label-tag text-slate-400 mb-2">Waiting</div>
                    <div class="text-5xl font-black text-yellow-400 tabular-nums" id="tv-waiting">{{ $waitingCount }}</div>
                    <div class="text-slate-500 text-xs mt-1">students in queue</div>
                </div>
            </div>

        </div>

        {{-- RIGHT: Queue List --}}
        <div class="flex flex-col shrink-0" style="width: 34%;">
            <div class="px-7 py-5 border-b border-white/10 shrink-0">
                <div class="label-tag text-slate-400">Queue List</div>
            </div>
            <div class="flex-1 overflow-hidden" id="tv-queue-list">
                @forelse($waitingList as $i => $entry)
                <div class="queue-row flex items-center gap-4 px-7 py-4">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                        {{ $i === 0 ? 'bg-yellow-400 text-slate-900' : 'bg-white/10 text-slate-400' }}">
                        {{ $i + 1 }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-white font-semibold text-sm truncate">{{ $entry->ticket_number }}</div>
                        <div class="text-slate-400 text-xs truncate">{{ $entry->purpose }}</div>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center h-full py-16 text-slate-600">
                    <i class="bi bi-inbox text-4xl mb-3"></i>
                    <div class="text-sm font-medium">No students waiting</div>
                </div>
                @endforelse
            </div>

            {{-- Est. wait time --}}
            <div class="shrink-0 px-7 pb-5 border-t border-white/10" style="background: rgba(255,255,255,.03); padding-top: 3.56rem;">
                <div class="label-tag text-slate-400 mb-1">Est. Wait Time</div>
                <div class="text-2xl font-black text-blue-400" id="tv-est-wait">
                    {{ $waitingCount > 0 ? '~' . ($waitingCount * 5) . ' min' : 'Waiting' }}
                </div>
                <div class="text-slate-600 text-xs mt-0.5">avg. 3 min per student</div>
            </div>
        </div>

    </div>

    {{-- ── TICKER ── --}}
    <div class="shrink-0 overflow-hidden border-t border-white/10 py-2.5"
         style="background: #1e3a8a;">
        <div class="ticker-inner text-blue-200 text-sm font-medium px-4">
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            🎓 Welcome to ACLC Mandaue Cashier's Office
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            Please have your requirements ready before your number is called
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            Get your virtual ticket at <strong>aclc-queue-system.up.railway.app</strong>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            SMS notifications will be sent when it's almost your turn
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            Thank you for your patience 🙏
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </div>
    </div>

<script>
// ── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    document.getElementById('tv-clock').innerText =
        now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true });
    document.getElementById('tv-date').innerText =
        now.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}
updateClock();
setInterval(updateClock, 1000);

// ── Helpers ────────────────────────────────────────────────────────────────
function flashEl(el) {
    el.classList.remove('flash');
    void el.offsetWidth; // reflow
    el.classList.add('flash');
}

function updateQueueList(waitingList) {
    const container = document.getElementById('tv-queue-list');
    if (!container) return;

    if (!waitingList || waitingList.length === 0) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full py-16 text-slate-600">
                <i class="bi bi-inbox text-4xl mb-3"></i>
                <div class="text-sm font-medium">No students waiting</div>
            </div>`;
        return;
    }

    container.innerHTML = waitingList.slice(0, 8).map((entry, i) => `
        <div class="queue-row flex items-center gap-4 px-7 py-4">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                ${i === 0 ? 'bg-yellow-400 text-slate-900' : 'bg-white/10 text-slate-400'}">
                ${i + 1}
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-white font-semibold text-sm truncate">${entry.ticket_number}</div>
                <div class="text-slate-400 text-xs truncate">${entry.purpose || ''}</div>
            </div>
        </div>
    `).join('');
}

// ── Pusher real-time ───────────────────────────────────────────────────────
if (window.PUSHER_APP_KEY) {
    const pusher = new Pusher(window.PUSHER_APP_KEY, {
        cluster: window.PUSHER_APP_CLUSTER || 'mt1',
        forceTLS: true
    });

    // Connection state indicator
    pusher.connection.bind('connected', () => {
        const dot = document.querySelector('.live-dot');
        if (dot) { dot.style.background = '#4ade80'; }
    });
    pusher.connection.bind('disconnected', () => {
        const dot = document.querySelector('.live-dot');
        if (dot) { dot.style.background = '#f87171'; }
    });

    pusher.subscribe('queue').bind('queue.updated', function(data) {
        const currentEl   = document.getElementById('tv-current');
        const nextEl      = document.getElementById('tv-next');
        const waitEl      = document.getElementById('tv-waiting');
        const estEl       = document.getElementById('tv-est-wait');
        const nameEl      = document.getElementById('tv-current-name');
        const purposeEl   = document.getElementById('tv-current-purpose');
        const purposeWrap = document.getElementById('tv-current-purpose-wrap');
        const nowServingBg = document.getElementById('tv-now-serving-bg');
        const breakBanner  = document.getElementById('tv-break-banner');

        if (currentEl && data.current && currentEl.innerText !== data.current) {
            currentEl.innerText = data.current;
            flashEl(currentEl);
        }

        if (nextEl && data.next) nextEl.innerText = data.next ?? 'Waiting';

        if (waitEl && data.waiting_count !== undefined) waitEl.innerText = data.waiting_count;

        if (estEl && data.avg_serve_mins !== undefined && data.waiting_count !== undefined) {
            estEl.innerText = data.waiting_count > 0
                ? '~' + Math.round(data.waiting_count * data.avg_serve_mins) + ' min'
                : 'Waiting';
        }

        if (data.current_serving) {
            if (nameEl) nameEl.innerText = data.current_serving.name || '';
            if (purposeEl) purposeEl.innerText = data.current_serving.purpose || '';
            if (purposeWrap) purposeWrap.classList.remove('hidden');
        } else {
            if (nameEl) nameEl.innerText = '';
            if (purposeWrap) purposeWrap.classList.add('hidden');
        }

        if (data.waiting_list) updateQueueList(data.waiting_list);

        // ── Pause/Resume real-time ──────────────────────────────────────
        if (data.queue_paused !== undefined) {
            const paused = data.queue_paused;
            if (nowServingBg) {
                nowServingBg.style.background = paused
                    ? 'linear-gradient(135deg, #78350f 0%, #92400e 50%, #b45309 100%)'
                    : 'linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%)';
            }
            if (breakBanner) breakBanner.classList.toggle('hidden', !paused);
        }
    });
} else {
    // Fallback polling every 5s
    setInterval(() => {
        fetch('{{ route("api.queueStatus") }}')
            .then(r => r.json())
            .then(data => {
                const c = document.getElementById('tv-current');
                const n = document.getElementById('tv-next');
                if (c && data.current) c.innerText = data.current;
                if (n && data.next) n.innerText = data.next;
            });
    }, 5000);
}
</script>
</body>
</html>
