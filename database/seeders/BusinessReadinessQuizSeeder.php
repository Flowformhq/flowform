<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessReadinessQuizSeeder extends Seeder
{
    public function run(): void
    {
        $radioType = FieldType::firstOrCreate(
            ['name' => 'radio'],
            ['component' => 'radio-input', 'is_active' => true]
        );

        $form = Form::updateOrCreate(
            ['slug' => 'enterprise-ai-readiness-diagnostic'],
            [
                'uuid' => 'e7b8c9d0-1234-4567-89ab-cdef01234567',
                'name' => 'Enterprise Agentic AI Readiness & Governance Diagnostic',
                'description' => '5-question executive diagnostic assessing organizational readiness, ROI modeling, and autonomous risk tiering.',
                'is_active' => true,
                'version' => 1,
            ]
        );

        $questions = [
            [
                'step_number' => 1,
                'title' => 'ROI & Use Case Prioritization',
                'code' => 'b_q1',
                'label' => 'When evaluating where to deploy autonomous agents in an enterprise, which criteria indicates the highest ROI and lowest deployment risk?',
                'options' => [
                    ['value' => 'a', 'label' => 'Replacing creative marketing copy generation across all brand channels.'],
                    ['value' => 'b', 'label' => 'High-frequency, deterministic business workflows with verified APIs, bounded error costs, and clear human approval gates.'],
                    ['value' => 'c', 'label' => 'Giving an unconstrained autonomous agent direct access to customer email inboxes with unsupervised send privileges.'],
                    ['value' => 'd', 'label' => 'Building a custom proprietary foundation model from scratch for general question-answering.'],
                ],
                'correct_value' => 'b',
                'domain' => 'ROI & Value Realization',
                'weight' => '25%',
                'explanation' => 'Agentic ROI is maximized in structured, high-volume processes with clean API access (e.g. KYC triage, invoice reconciliation, automated technical support research) where failure cost is bounded and verifiable.',
            ],
            [
                'step_number' => 2,
                'title' => 'Autonomous Authority & Governance',
                'code' => 'b_q2',
                'label' => 'Under modern AI governance standards, how should an enterprise structure autonomous authority for actions involving financial transactions or external data alteration?',
                'options' => [
                    ['value' => 'a', 'label' => 'Full autonomous authority without logging to maximize agent execution speed.'],
                    ['value' => 'b', 'label' => 'Multi-tier authorization: read-only tasks execute autonomously, while state-modifying or financial transactions require explicit Human-in-the-Loop (HITL) approval.'],
                    ['value' => 'c', 'label' => 'Total prohibition of all autonomous AI systems across the organization.'],
                    ['value' => 'd', 'label' => 'Rely solely on the AI model vendor terms of service for legal liability protection.'],
                ],
                'correct_value' => 'b',
                'domain' => 'Governance & Risk Tiering',
                'weight' => '25%',
                'explanation' => 'Enterprise governance requires a deterministic tiering matrix: Tier 1 (Informational/Read-Only) operates with supervised autonomy; Tier 2/3 (Financial, PII, Production Writes) requires deterministic HITL escalation gates.',
            ],
            [
                'step_number' => 3,
                'title' => 'TCO & Token Economics',
                'code' => 'b_q3',
                'label' => 'What is the primary driver of unpredictable operational expenditure (OpEx) when scaling multi-agent architectures in production?',
                'options' => [
                    ['value' => 'a', 'label' => 'Initial cloud server hosting hardware costs.'],
                    ['value' => 'b', 'label' => 'Quadratic context window bloat and runaway recursive feedback loops between unconstrained subagents.'],
                    ['value' => 'c', 'label' => 'Developer IDE subscription costs.'],
                    ['value' => 'd', 'label' => 'Domain name registration renewals.'],
                ],
                'correct_value' => 'b',
                'domain' => 'Token Economics & TCO',
                'weight' => '20%',
                'explanation' => 'Multi-agent loops without strict turn ceilings, context compression, and loopback daemons cause exponential token consumption. A single rogue loop can burn hundreds of dollars in hours without delivering business output.',
            ],
            [
                'step_number' => 4,
                'title' => 'Shadow AI & Data Privacy',
                'code' => 'b_q4',
                'label' => 'What is the most effective policy for preventing proprietary enterprise data leakage while enabling business teams to leverage agentic workflows?',
                'options' => [
                    ['value' => 'a', 'label' => 'Banning all external internet connectivity on company employee laptops.'],
                    ['value' => 'b', 'label' => 'Deploying private gateway proxies with tokenized PII masking, centralized audit telemetry, and explicit zero-data-retention vendor agreements.'],
                    ['value' => 'c', 'label' => 'Trusting employees to only use personal consumer chat accounts for internal documents.'],
                    ['value' => 'd', 'label' => 'Assuming that small language models cannot leak confidential information.'],
                ],
                'correct_value' => 'b',
                'domain' => 'Data Privacy & Shadow AI',
                'weight' => '15%',
                'explanation' => 'Enterprises must provide governed, sanctioned infrastructure with automated PII redaction and loopback monitoring, eliminating the incentive for employees to route sensitive business data through unmonitored consumer tools.',
            ],
            [
                'step_number' => 5,
                'title' => 'Workforce & Change Management',
                'code' => 'b_q5',
                'label' => 'How should executive leadership measure the organizational impact of introducing an agentic digital workforce?',
                'options' => [
                    ['value' => 'a', 'label' => 'Counting the raw number of prompts entered by team members each week.'],
                    ['value' => 'b', 'label' => 'Measuring cycle time reduction, throughput per employee, error rates, and employee shift toward strategic decision-making.'],
                    ['value' => 'c', 'label' => 'Immediate headcount reduction on day 1 of pilot rollout.'],
                    ['value' => 'd', 'label' => 'Surveying employees on whether they like chatting with LLMs.'],
                ],
                'correct_value' => 'b',
                'domain' => 'Organizational Transformation',
                'weight' => '15%',
                'explanation' => 'Leading enterprises evaluate hybrid human-agent productivity through verified operational metrics: cycle time compression, reduced backlog latency, and liberated human bandwidth for high-judgment client advisory.',
            ],
        ];

        foreach ($questions as $q) {
            $step = Step::updateOrCreate(
                [
                    'form_id' => $form->id,
                    'step_number' => $q['step_number'],
                ],
                [
                    'title' => $q['title'],
                    'description' => "Question {$q['step_number']} of 5",
                    'is_visible' => true,
                    'meta' => [
                        'domain' => $q['domain'],
                        'weight' => $q['weight'],
                        'correct_value' => $q['correct_value'],
                        'explanation' => $q['explanation'],
                    ],
                ]
            );

            $field = Field::updateOrCreate(
                [
                    'form_id' => $form->id,
                    'step_id' => $step->id,
                    'code' => $q['code'],
                ],
                [
                    'field_type_id' => $radioType->id,
                    'label' => $q['label'],
                    'is_required' => true,
                    'order' => 1,
                    'config' => [
                        'correct_value' => $q['correct_value'],
                        'domain' => $q['domain'],
                        'blueprint_weight' => $q['weight'],
                        'explanation' => $q['explanation'],
                    ],
                ]
            );

            foreach ($q['options'] as $idx => $opt) {
                FieldOption::updateOrCreate(
                    [
                        'field_id' => $field->id,
                        'value' => $opt['value'],
                    ],
                    [
                        'label' => $opt['label'],
                        'order' => $idx + 1,
                    ]
                );
            }
        }
    }
}
