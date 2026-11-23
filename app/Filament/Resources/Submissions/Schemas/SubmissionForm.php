<?php

declare(strict_types=1);

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->disabled(),
                TextInput::make('form.name')
                    ->label('Form')
                    ->disabled(),
                TextInput::make('status')
                    ->disabled(),
                KeyValue::make('data')
                    ->label('Submitted Payload Data')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state)) {
                            return [];
                        }

                        $formatted = [];
                        foreach ($state as $k => $v) {
                            $formatted[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : $v;
                        }

                        return $formatted;
                    })
                    ->disabled()
                    ->columnSpanFull()
                    ->visible(fn ($record) => ! empty($record?->data)),
                Placeholder::make('uploaded_files')
                    ->label('Uploaded Files')
                    ->content(function ($record) {
                        if (! $record) {
                            return null;
                        }

                        $files = $record->getUploadedFiles();
                        if (empty($files)) {
                            return 'No files uploaded.';
                        }

                        $html = '<ul class="space-y-2">';
                        foreach ($files as $index => $file) {
                            $url = route('admin.submissions.files.download', ['submission' => $record->id, 'index' => $index]);
                            $size = number_format(($file['size'] ?? 0) / 1024, 1).' KB';
                            $name = e($file['name'] ?? 'File');
                            $html .= "<li><a href=\"{$url}\" class=\"text-cyan-400 hover:underline font-medium inline-flex items-center gap-1.5\" target=\"_blank\"><span>📎 {$name}</span> <span class=\"text-xs text-slate-400 font-mono\">({$size})</span></a></li>";
                        }
                        $html .= '</ul>';

                        return new HtmlString($html);
                    })
                    ->visible(fn ($record) => ! empty($record?->getUploadedFiles()))
                    ->columnSpanFull(),
                KeyValue::make('meta')
                    ->label('Submission Metadata')
                    ->disabled()
                    ->columnSpanFull()
                    ->visible(fn ($record) => ! empty($record?->meta)),
            ]);
    }
}
