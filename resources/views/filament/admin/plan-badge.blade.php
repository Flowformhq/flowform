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
                $credits = $user->credits_balance ?? 0;
            @endphp
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2.5 py-1 text-xs font-mono text-slate-300 border border-slate-700" title="{{ $used }}/{{ $quota }} free monthly submissions used">
                    {{ $used }}/{{ $quota }} free
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-cyan-950/60 px-2.5 py-1 text-xs font-mono text-cyan-300 border border-cyan-800" title="~{{ number_format($user->estimatedEmailSubmissions()) }} emails or ~{{ number_format($user->estimatedWebhookSubmissions()) }} webhooks">
                    ⚡ {{ number_format($credits) }} credits
                </span>
                <a href="{{ route('billing.upgrade', ['pack' => 1]) }}"
                   class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-cyan-500 to-indigo-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:from-cyan-400 hover:to-indigo-500 transition"
                   title="Buy 5,000 credits for $1 USDT/USDC (Never expires)">
                    Top Up $1 (5k Credits) →
                </a>
            </div>
        @endif
    </div>
@endif
