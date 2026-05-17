<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubmissionsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Submissions', Submission::count()),
            Stat::make('Draft', Submission::where('status', 'draft')->count())
                ->color('warning'),
            Stat::make('Completed', Submission::where('status', 'completed')->count())
                ->color('success'),
        ];
    }
}
