<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Form;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FormsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Forms', Form::count()),
            Stat::make('Active Forms', Form::where('is_active', true)->count())
                ->color('success'),
        ];
    }
}
