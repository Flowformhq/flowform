@php
    $user = auth()->user();
@endphp

@if ($user)
    <div class="flex items-center gap-2 pl-2">
        @if ($user->isPro())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400 border border-emerald-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                FlowForm Pro (Active)
            </span>
        @else
            @php
                $used = $user->currentMonthSubmissionsCount();
                $quota = $user->monthly_quota ?? 50;
            @endphp
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2.5 py-1 text-xs font-mono text-slate-300 border border-slate-700">
                    {{ $used }}/{{ $quota }} submissions
                </span>
                <a href="{{ route('billing.upgrade') }}"
                   class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-cyan-500 to-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:from-cyan-400 hover:to-indigo-500 transition">
                    Upgrade to Pro ($9 USDC/USDT) →
                </a>
            </div>
        @endif
    </div>
@endif
