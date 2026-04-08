<?php

namespace Database\Seeders;

use App\Models\FieldType;
use Illuminate\Database\Seeder;

class FieldTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'text', 'component' => 'text-input', 'meta' => null],
            ['name' => 'email', 'component' => 'email-input', 'meta' => null],
            ['name' => 'number', 'component' => 'number-input', 'meta' => null],
            ['name' => 'textarea', 'component' => 'textarea-input', 'meta' => null],
            ['name' => 'select', 'component' => 'select-input', 'meta' => null],
            ['name' => 'checkbox', 'component' => 'checkbox-input', 'meta' => null],
            ['name' => 'radio', 'component' => 'radio-input', 'meta' => null],
            ['name' => 'date', 'component' => 'date-input', 'meta' => null],
            ['name' => 'file', 'component' => 'file-input', 'meta' => null],
            ['name' => 'heading', 'component' => 'heading-display', 'meta' => ['is_display' => true]],
            ['name' => 'paragraph', 'component' => 'paragraph-display', 'meta' => ['is_display' => true]],
        ];

        foreach ($types as $type) {
            FieldType::create($type);
        }
    }
}
