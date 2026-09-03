<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Illuminate\Database\Seeder;

class NvidiaQuizSeeder extends Seeder
{
    public function run(): void
    {
        $radioType = FieldType::where('name', 'radio')->first();
        if (! $radioType) {
            return;
        }

        $form = Form::updateOrCreate(
            ['slug' => 'nvidia-ncp-aai-diagnostic'],
            [
                'name' => 'NVIDIA NCP-AAI Certification Practice Diagnostic',
                'description' => '10 blueprint-mapped practice questions based on the official NVIDIA Agentic AI Professional blueprint.',
                'is_active' => true,
                'version' => 1,
            ]
        );

        $questions = [
            [
                'domain' => 'Agent Architecture & Design',
                'weight' => 15,
                'question' => 'When designing a multi-agent system requiring specialized role separation (implementer, reviewer, orchestrator), which communication topology minimizes token bloat and prevent circular delegation?',
                'options' => [
                    ['value' => 'a', 'label' => 'Full-mesh peer-to-peer broadcast where every agent reads all conversation history.'],
                    ['value' => 'b', 'label' => 'Hierarchical supervisor topology with strictly scoped context handovers and unidirectional reporting.'],
                    ['value' => 'c', 'label' => 'Single shared monolithic context window with append-only system logs.'],
                    ['value' => 'd', 'label' => 'Round-robin token ring where agents execute in fixed arbitrary sequence.'],
                ],
                'correct' => 'b',
                'explanation' => 'Hierarchical supervisor patterns scope context to specific subagent objectives, preventing quadratic context bloat and endless conversational ping-pong.',
                'citation' => 'NVIDIA NCP-AAI Blueprint Domain 1: Agent Architecture & Design',
            ],
            [
                'domain' => 'Agent Development',
                'weight' => 15,
                'question' => 'An autonomous coding agent encounters a non-zero exit code while running unit tests. According to resilient agent development patterns, what is the best initial recovery action?',
                'options' => [
                    ['value' => 'a', 'label' => 'Immediately regenerate the entire project codebase from scratch.'],
                    ['value' => 'b', 'label' => 'Inspect stderr and test failure logs, formulate a minimal test-first hypothesis, and apply an isolated patch.'],
                    ['value' => 'c', 'label' => 'Disable the failing test assertion to force CI to pass.'],
                    ['value' => 'd', 'label' => 'Prompt the user to manually fix the code and restart the session.'],
                ],
                'correct' => 'b',
                'explanation' => 'Agent development best practices emphasize tight deterministic feedback loops: reproduce, isolate failing output, formulate a scoped diff, and re-verify without destructive full rewrites.',
                'citation' => 'NVIDIA NCP-AAI Blueprint Domain 2: Agent Development',
            ],
            [
                'domain' => 'Evaluation & Tuning',
                'weight' => 13,
                'question' => 'Why are deterministic offline contract evaluations (dry-run checks) prioritized as a primary PR gate over live model-scored evaluations?',
                'options' => [
                    ['value' => 'a', 'label' => 'LLM evaluation is impossible for agent skills.'],
                    ['value' => 'b', 'label' => 'Deterministic checks provide zero-cost, reproducible, instant pass/fail regression gates without stochastic variance or API token costs.'],
                    ['value' => 'c', 'label' => 'Live models cannot inspect markdown or code files.'],
                    ['value' => 'd', 'label' => 'Offline checks guarantee 100% test accuracy across all edge cases.'],
                ],
                'correct' => 'b',
                'explanation' => 'Offline contract checks catch structural, schema, and policy regressions deterministically on every PR for free, reserving expensive live model runs for periodic benchmark evaluations.',
                'citation' => 'NVIDIA NCP-AAI Blueprint Domain 3: Evaluation & Tuning',
            ],
            [
                'domain' => 'Deployment & Scaling',
                'weight' => 13,
                'question' => 'When hosting long-running agentic daemon processes (such as OpenClaw or headless worker gateways) on Linux, which pattern ensures persistence and loopback security?',
                'options' => [
                    ['value' => 'a', 'label' => 'Run the process in an unmanaged background subshell exposed on 0.0.0.0.'],
                    ['value' => 'b', 'label' => 'Use systemd service units bound to 127.0.0.1 with persistent environment tokens and automatic restart policies.'],
                    ['value' => 'c', 'label' => 'Commit API secrets into git repositories for easy runtime discovery.'],
                    ['value' => 'd', 'label' => 'Execute within a temporary tmux session and reboot the server nightly.'],
                ],
                'correct' => 'b',
                'explanation' => 'Persistent systemd service daemons bound to loopback prevent unauthorized external ingress while providing automated process lifecycle management and restart resilience.',
                'citation' => 'NVIDIA NCP-AAI Blueprint Domain 4: Deployment & Scaling',
            ],
            [
                'domain' => 'Cognition, Planning & Memory',
                'weight' => 10,
                'question' => 'Under what operating condition should an engineering agent trigger a formal context handover rather than continuing in the current session?',
                'options' => [
                    ['value' => 'a', 'label' => 'After completing any single git commit.'],
                    ['value' => 'b', 'label' => 'When context usage exceeds 40% on unfinished multi-step work or when 5-10% of the session token ceiling remains.'],
                    ['value' => 'c', 'label' => 'Whenever a tool call returns a warning.'],
                    ['value' => 'd', 'label' => 'Only when the user explicitly types /quit.'],
                ],
                'correct' => 'b',
                'explanation' => 'Context degradation and token limits impair agent reasoning. Compacting running state into a structured handover document preserves decisions before degradation causes hallucinations.',
                'citation' => 'NVIDIA NCP-AAI Blueprint Domain 5: Cognition, Planning & Memory',
            ],
        ];

        foreach ($questions as $index => $q) {
            $stepNumber = $index + 1;
            $step = Step::updateOrCreate(
                ['form_id' => $form->id, 'step_number' => $stepNumber],
                [
                    'title' => $q['domain'],
                    'description' => "Question {$stepNumber} of " . count($questions),
                    'is_visible' => true,
                    'meta' => [
                        'blueprint_domain' => $q['domain'],
                        'weight' => $q['weight'],
                        'correct_value' => $q['correct'],
                        'explanation' => $q['explanation'],
                        'citation' => $q['citation'],
                    ],
                ]
            );

            $field = Field::updateOrCreate(
                ['form_id' => $form->id, 'step_id' => $step->id, 'code' => "q{$stepNumber}"],
                [
                    'field_type_id' => $radioType->id,
                    'label' => $q['question'],
                    'is_required' => true,
                    'order' => 1,
                    'config' => [
                        'correct_value' => $q['correct'],
                        'explanation' => $q['explanation'],
                        'domain' => $q['domain'],
                    ],
                ]
            );

            foreach ($q['options'] as $optIndex => $opt) {
                FieldOption::updateOrCreate(
                    ['field_id' => $field->id, 'value' => $opt['value']],
                    [
                        'label' => $opt['label'],
                        'order' => $optIndex + 1,
                    ]
                );
            }
        }
    }
}
