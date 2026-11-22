<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Form;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class FormsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = Auth::user();
        $isAdmin = $user?->is_admin ?? false;

        $formsQuery = Form::query()->when(! $isAdmin, fn ($q) => $q->where('user_id', $user?->id));
        $submissionsQuery = Submission::query()->when(! $isAdmin, fn ($q) => $q->whereHas('form', fn ($f) => $f->where('user_id', $user?->id)));

        $totalForms = (clone $formsQuery)->count();
        $activeForms = (clone $formsQuery)->where('is_active', true)->count();
        $thisMonthSubmissions = (clone $submissionsQuery)->where('created_at', '>=', now()->startOfMonth())->count();

        $planLabel = $user?->isPro() ? 'Pro Tier' : 'Free Tier';
        $quota = $user?->isPro() ? '10,000 / mo' : '50 / mo';
        $planColor = $user?->isPro() ? 'success' : 'warning';
        $planDesc = $user?->isPro()
            ? 'USDC / USDT Stablecoin plan active'
            : "{$thisMonthSubmissions} / 50 free monthly used";

        $credits = $user?->credits_balance ?? 0;
        $estEmails = $user?->estimatedEmailSubmissions() ?? 0;
        $estWebhooks = $user?->estimatedWebhookSubmissions() ?? 0;

        return [
            Stat::make('Your Plan', $planLabel)
                ->description($planDesc)
                ->color($planColor),
            Stat::make('Pay-As-You-Go Credits', number_format($credits))
                ->description("~" . number_format($estEmails) . " emails OR ~" . number_format($estWebhooks) . " webhooks")
                ->descriptionIcon('heroicon-m-bolt')
                ->color($credits > 100 ? 'success' : ($credits > 0 ? 'warning' : 'danger')),
            Stat::make('Active Forms', $activeForms)
                ->description("Total forms: {$totalForms}")
                ->color('info'),
            Stat::make('Monthly Ingest', $thisMonthSubmissions)
                ->description("Limit: {$quota}")
                ->color($thisMonthSubmissions >= 50 && ! $user?->isPro() && $credits <= 0 ? 'danger' : 'success'),
        ];
    }
}
