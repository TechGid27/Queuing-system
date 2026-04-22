<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ACLC Queue System') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#2563eb', dark: '#1d4ed8', light: '#dbeafe' },
                        sidebar: '#0f172a',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
    <script>
        window.PUSHER_APP_KEY     = "{{ config('broadcasting.connections.pusher.key') }}";
        window.PUSHER_APP_CLUSTER = "{{ config('broadcasting.connections.pusher.options.cluster') }}";
        window.PUSHER_HOST        = "{{ config('broadcasting.connections.pusher.options.host', '') }}";
        window.PUSHER_PORT        = "{{ config('broadcasting.connections.pusher.options.port', 443) }}";
        window.PUSHER_SCHEME      = "{{ config('broadcasting.connections.pusher.options.scheme', 'https') }}";
        
        // Initialize Laravel Echo with Pusher
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: window.PUSHER_APP_KEY,
            cluster: window.PUSHER_APP_CLUSTER,
            forceTLS: window.PUSHER_SCHEME === 'https',
        });
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .ticket-xl  { font-size: clamp(3rem, 10vw, 6rem); font-weight: 900; letter-spacing: -0.05em; line-height: 1; }
        .ticket-lg  { font-size: clamp(2rem, 6vw, 3.5rem); font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
        .badge-live { animation: pulse-live 2s infinite; }
        @keyframes pulse-live { 0%,100%{opacity:1} 50%{opacity:.5} }
        .sidebar-link { transition: background .15s, color .15s; }
        [x-cloak] { display: none !important; }
    </style>
    @yield('styles')
</head>
<body class="bg-slate-100 text-slate-900 antialiased">

@auth
{{-- ── Authenticated Layout (with sidebar) ── --}}

{{-- Mobile overlay --}}
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

{{-- Sidebar --}}
<aside id="sidebar" class="fixed top-0 left-0 bottom-0 w-60 bg-slate-900 flex flex-col z-30 -translate-x-full lg:translate-x-0 transition-transform duration-200">
    {{-- Brand --}}
    <div class="px-5 py-6 border-b border-white/5">
        <div class="text-white font-bold text-sm tracking-tight">ACLC Mandaue</div>
        <div class="text-slate-400 text-xs mt-0.5">Cashier's Office</div>
        <span class="inline-block mt-2 bg-primary text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Queue System</span>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-3 py-4 overflow-y-auto">
        @if(auth()->user()->role === 'staff')
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest px-2 mb-2">Management</div>
            <a href="{{ route('admin.index') }}"
               class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium mb-0.5
                      {{ request()->routeIs('admin.index') ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                <i class="bi bi-speedometer2 w-4 text-center"></i> Dashboard
            </a>
            <a href="{{ route('admin.purposes.index') }}"
               class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium mb-0.5
                      {{ request()->routeIs('admin.purposes.*') ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                <i class="bi bi-tags w-4 text-center"></i> Purposes
            </a>
            <a href="{{ route('admin.reports') }}"
               class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium mb-0.5
                      {{ request()->routeIs('admin.reports') ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                <i class="bi bi-bar-chart-line w-4 text-center"></i> Reports
            </a>
        @else
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest px-2 mb-2">Student</div>
            <a href="{{ route('student.index') }}"
               class="sidebar-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium mb-0.5
                      {{ request()->routeIs('student.index') ? 'bg-primary text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                <i class="bi bi-ticket-perforated w-4 text-center"></i> My Queue
            </a>
        @endif
    </nav>

    {{-- User footer --}}
    <div class="px-3 py-4 border-t border-white/5">
        <div class="flex items-center gap-2.5 px-2 py-2 mb-2">
            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold shrink-0">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="text-slate-200 text-xs font-semibold truncate">{{ auth()->user()->name }}</div>
                <div class="text-slate-500 text-[11px]">{{ ucfirst(auth()->user()->role) }}</div>
            </div>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-slate-400 text-xs font-semibold border border-white/10 hover:bg-white/5 hover:text-slate-200 transition-colors">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </button>
        </form>
    </div>
</aside>

{{-- Main content --}}
<div class="lg:ml-60 min-h-screen flex flex-col">
    {{-- Topbar --}}
    <header class="sticky top-0 z-10 bg-white border-b border-slate-200 px-4 lg:px-7 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
            {{-- Mobile hamburger --}}
            <button onclick="toggleSidebar()" class="lg:hidden p-1.5 rounded-lg text-slate-500 hover:bg-slate-100">
                <i class="bi bi-list text-xl"></i>
            </button>
            <span class="text-sm font-semibold text-slate-800">@yield('page-title', 'Dashboard')</span>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 hidden sm:block" id="topbar-clock">{{ now()->format('h:i A') }}</span>
        </div>
    </header>

    {{-- Page content --}}
    <main class="flex-1 p-4 lg:p-7">
        @if(session('success'))
            <div class="flex items-center gap-2.5 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-check-circle-fill text-green-500"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="flex items-center gap-2.5 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-exclamation-triangle-fill text-yellow-500"></i> {{ session('warning') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center gap-2.5 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl mb-5">
                <i class="bi bi-x-circle-fill text-red-500"></i> {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>

@else
{{-- ── Guest / Auth Layout (no sidebar, centered) ── --}}
<div class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    @if(session('success'))
        <div class="fixed top-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl shadow-lg">
            <i class="bi bi-check-circle-fill text-green-500"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="fixed top-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm px-4 py-3 rounded-xl shadow-lg">
            <i class="bi bi-exclamation-triangle-fill text-yellow-500"></i> {{ session('warning') }}
        </div>
    @endif
    @yield('content')
</div>
@endauth

{{-- Toast container --}}
<div id="toast-wrap" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<script>
    // Topbar clock
    const clockEl = document.getElementById('topbar-clock');
    if (clockEl) setInterval(() => {
        clockEl.innerText = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }, 1000);

    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar  = document.getElementById('sidebar');
        const overlay  = document.getElementById('sidebar-overlay');
        const isOpen   = !sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('-translate-x-full', isOpen);
        overlay.classList.toggle('hidden', isOpen);
    }

    // Toast helper
    function showToast(message, type = 'success') {
        const wrap = document.getElementById('toast-wrap');
        if (!wrap) return;
        const colors = { success: 'bg-slate-900 border-l-4 border-green-500', warning: 'bg-slate-900 border-l-4 border-yellow-500' };
        const icons  = { success: 'check-circle', warning: 'exclamation-circle' };
        const el = document.createElement('div');
        el.className = `pointer-events-auto flex items-center gap-3 ${colors[type] || colors.success} text-white text-sm font-medium px-4 py-3 rounded-xl shadow-xl max-w-xs`;
        el.innerHTML = `<i class="bi bi-${icons[type] || 'check-circle'}"></i><span>${message}</span>`;
        el.style.animation = 'slideIn .25s ease';
        wrap.appendChild(el);
        setTimeout(() => el.remove(), 4500);
    }
</script>
<style>
    @keyframes slideIn { from{transform:translateX(40px);opacity:0} to{transform:translateX(0);opacity:1} }
</style>
@yield('scripts')
</body>
</html>
