@php
    $isDemo = str_contains(request()->getHost(), 'demo')
        || request()->has('demo')
        || (bool) config('app.is_demo', false);
@endphp

@if($isDemo)
    <div class="mb-5 rounded-xl border border-cyan-500/30 bg-cyan-950/20 p-3.5 text-xs text-cyan-200 shadow-sm backdrop-blur-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                </span>
                <span class="font-semibold text-cyan-300 shrink-0">Live Demo:</span>
                <span class="text-cyan-100/90 truncate">Credentials prefilled below. Click sign in to explore.</span>
            </div>
            <span class="font-mono text-[11px] text-cyan-400/80 shrink-0 hidden sm:inline">admin@flowformhq.com</span>
        </div>
    </div>
@endif
