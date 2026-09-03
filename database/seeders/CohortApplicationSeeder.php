<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Illuminate\Database\Seeder;

class CohortApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $textType = FieldType::where('name', 'text')->first();
        $emailType = FieldType::where('name', 'email')->first();
        $selectType = FieldType::where('name', 'select')->first();
        $radioType = FieldType::where('name', 'radio')->first();
        $textareaType = FieldType::where('name', 'textarea')->first();

        if (! $textType || ! $emailType || ! $selectType || ! $radioType || ! $textareaType) {
            return;
        }

        $form = Form::updateOrCreate(
            ['slug' => 'cohort-application-intake'],
            [
                'name' => 'Agentic AI 101: Cohort Application & Intake',
                'description' => '4-Hour Live VPS Portfolio Build Cohort Application with Waqas Ahmed.',
                'is_active' => true,
                'version' => 1,
            ]
        );

        // Step 1: Contact
        $step1 = Step::updateOrCreate(
            ['form_id' => $form->id, 'step_number' => 1],
            [
                'title' => 'Engineer Profile',
                'description' => 'Your contact and development background',
                'is_visible' => true,
            ]
        );

        Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step1->id, 'code' => 'full_name'],
            [
                'field_type_id' => $textType->id,
                'label' => 'Full Name',
                'placeholder' => 'e.g. Alex Mercer',
                'is_required' => true,
                'order' => 1,
            ]
        );

        Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step1->id, 'code' => 'work_email'],
            [
                'field_type_id' => $emailType->id,
                'label' => 'Work / Preferred Email',
                'placeholder' => 'alex@company.com',
                'is_required' => true,
                'order' => 2,
            ]
        );

        Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step1->id, 'code' => 'github_username'],
            [
                'field_type_id' => $textType->id,
                'label' => 'GitHub Username',
                'placeholder' => 'e.g. alexmercer',
                'is_required' => true,
                'order' => 3,
            ]
        );

        // Step 2: Experience & Stack
        $step2 = Step::updateOrCreate(
            ['form_id' => $form->id, 'step_number' => 2],
            [
                'title' => 'Technical Background',
                'description' => 'Your development stack and infrastructure familiarity',
                'is_visible' => true,
            ]
        );

        $stackField = Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step2->id, 'code' => 'primary_stack'],
            [
                'field_type_id' => $selectType->id,
                'label' => 'Primary Programming Language / Stack',
                'is_required' => true,
                'order' => 1,
            ]
        );

        $stacks = [
            ['value' => 'typescript', 'label' => 'TypeScript / Node.js'],
            ['value' => 'python', 'label' => 'Python'],
            ['value' => 'php', 'label' => 'PHP / Laravel'],
            ['value' => 'go_rust', 'label' => 'Go / Rust'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        foreach ($stacks as $i => $s) {
            FieldOption::updateOrCreate(
                ['field_id' => $stackField->id, 'value' => $s['value']],
                ['label' => $s['label'], 'order' => $i + 1]
            );
        }

        $vpsField = Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step2->id, 'code' => 'vps_experience'],
            [
                'field_type_id' => $radioType->id,
                'label' => 'Linux / VPS Comfort Level',
                'is_required' => true,
                'order' => 2,
            ]
        );

        $vpsOpts = [
            ['value' => 'beginner', 'label' => 'Beginner (Have not configured Linux servers via SSH)'],
            ['value' => 'intermediate', 'label' => 'Intermediate (Comfortable with SSH, basic systemd/Nginx)'],
            ['value' => 'advanced', 'label' => 'Advanced (Manage production cloud VMs, Docker, or Kubernetes)'],
        ];

        foreach ($vpsOpts as $i => $o) {
            FieldOption::updateOrCreate(
                ['field_id' => $vpsField->id, 'value' => $o['value']],
                ['label' => $o['label'], 'order' => $i + 1]
            );
        }

        // Step 3: Goals
        $step3 = Step::updateOrCreate(
            ['form_id' => $form->id, 'step_number' => 3],
            [
                'title' => 'Project Goals',
                'description' => 'What you aim to build in the live 4-hour session',
                'is_visible' => true,
            ]
        );

        Field::updateOrCreate(
            ['form_id' => $form->id, 'step_id' => $step3->id, 'code' => 'target_project'],
            [
                'field_type_id' => $textareaType->id,
                'label' => 'What autonomous agent system or tool do you want to build?',
                'placeholder' => 'e.g. An autonomous PR code review agent with PreToolUse guardrails, or a multi-agent crawler with memory handovers...',
                'is_required' => true,
                'order' => 1,
            ]
        );
    }
}
