<?php

namespace App\Filament\Exports;

use App\Models\Submission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SubmissionExporter extends Exporter
{
    protected static ?string $model = Submission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('form.name')->label('Form'),
            ExportColumn::make('status'),
            ExportColumn::make('current_step')->label('Current Step'),
            ExportColumn::make('created_at')->label('Submitted At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Your submission export has completed and {$count} ".str('row')->plural($export->successful_rows).' exported.';
    }
}
