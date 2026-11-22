<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class SubmissionsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = Auth::user();
        $isAdmin = $user?->is_admin ?? false;

        $submissionsQuery = Submission::query()
            ->when(! $isAdmin, fn ($q) => $q->whereHas('form', fn ($f) => $f->where('user_id', $user?->id)));

        return [
            Stat::make('Total Submissions', (clone $submissionsQuery)->count()),
            Stat::make('Draft', (clone $submissionsQuery)->where('status', 'draft')->count())
                ->color('warning'),
            Stat::make('Completed', (clone $submissionsQuery)->where('status', 'completed')->count())
                ->color('success'),
        ];
    }
}
