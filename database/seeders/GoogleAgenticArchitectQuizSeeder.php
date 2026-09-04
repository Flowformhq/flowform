<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Illuminate\Database\Seeder;

class GoogleAgenticArchitectQuizSeeder extends Seeder
{
    public function run(): void
    {
        $radioType = FieldType::where('name', 'radio')->first();
        if (! $radioType) {
            return;
        }

        $form = Form::updateOrCreate(
            ['slug' => 'google-agentic-architect-diagnostic'],
            [
                'name' => 'Google Cloud Professional Agentic Architect Exam Diagnostic',
                'description' => 'Comprehensive syllabus-mapped practice diagnostic correlated to the official Google Cloud Professional Agentic Architect blueprint and Skills Path 4525.',
                'is_active' => true,
                'version' => 1,
            ]
        );

        $questions = [
            [
                'domain' => 'Section 1: Low-Code Tools',
                'weight' => 13,
                'question' => 'You are designing a customer onboarding agent using Customer Experience Agent Studio (CX Agent Studio). The agent must collect an account ID, query enterprise status, and if the user requests human escalation at any point in the conversation, immediately trigger an escalation workflow. According to official Google Cloud CX Agent Studio architecture, how should this workflow be configured?',
                'options' => [
                    ['value' => 'a', 'label' => 'Create a single monolithic Start Page with condition rules checking intent on every parameter prompt.'],
                    ['value' => 'b', 'label' => 'Configure an event handler on the page for event sys.intent.escalate with a Transition Route to the escalation page.'],
                    ['value' => 'c', 'label' => 'Deploy a webhook that continuously polls Dialogflow fulfillment state on every utterance.'],
                    ['value' => 'd', 'label' => 'Use a form-filling loop without transition routes until all slots are filled.'],
                ],
                'correct' => 'b',
                'explanation' => 'CX Agent Studio uses state-based workflows where Pages represent conversational states, Transition Routes define conditional or intent-based transitions, and Event Handlers catch system/user triggers (e.g. sys.intent.escalate) to transition seamlessly.',
                'citation' => 'Exam Guide Section 1.1: Configuring agentic workflows using low-code tools (CX Agent Studio)',
                'source_course' => 'Understand Google Cloud Agents (Course 1504)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1504',
            ],
            [
                'domain' => 'Section 2: Coding Agents & Agent Skills',
                'weight' => 17,
                'question' => 'In which of the following scenarios should you consider using Agent Skills?',
                'options' => [
                    ['value' => 'a', 'label' => 'When you want to ensure the agent restarts from a fresh memory with a blank slate every session.'],
                    ['value' => 'b', 'label' => 'When an agent needs to follow a multistep standard workflow, capture company domain expertise, manage unpredictable AI, or avoid repeating context.'],
                    ['value' => 'c', 'label' => 'When you want the agent to rely solely on conversation rather than any additional references.'],
                    ['value' => 'd', 'label' => 'When you want to increase token consumption.'],
                ],
                'correct' => 'b',
                'explanation' => 'Agent Skills organize enterprise workflows into modular, reusable instruction packages. They enable coding agents to consistently execute complex multi-step procedures, capture proprietary company expertise, guide stochastic LLM behavior deterministically, and avoid repeating instructions across sessions.',
                'citation' => 'Exam Guide Section 2.1 & 2.2: Coding Agents & Antigravity Customizations',
                'source_course' => 'Build Agent Skills with Google (Course 1832)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1832',
            ],
            [
                'domain' => 'Section 2: Coding Agents & Sandboxing',
                'weight' => 17,
                'question' => 'An engineering team wants to orchestrate coding agents to build and deploy applications while guaranteeing source code security and preventing unintended tool calls. According to Google Cloud Section 2 recommendations, what environment and customization model should be used?',
                'options' => [
                    ['value' => 'a', 'label' => 'Run coding agents with full sudo permissions directly on bare-metal production developer laptops.'],
                    ['value' => 'b', 'label' => 'Configure coding agents inside isolated sandboxes (GKE / Cloud Workstations / Antigravity) with Model Context Protocol (MCP) servers and deterministic PreToolUse guardrail hooks.'],
                    ['value' => 'c', 'label' => 'Bypass sandboxing and pass database credentials directly in chat prompts.'],
                    ['value' => 'd', 'label' => 'Disable git version control and permit agents to push commits directly to main without review.'],
                ],
                'correct' => 'b',
                'explanation' => 'Section 2 specifies that coding agents should run within isolated sandboxes (GKE, Cloud Workstations, Antigravity) augmented with MCP servers for controlled tool access, Skills for progressive context disclosure, and PreToolUse hooks for deterministic execution guardrails.',
                'citation' => 'Exam Guide Section 2.1: Using coding agents effectively',
                'source_course' => 'Liftoff with Google Antigravity: Build a Video Game with AI (Course 1685)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1685',
            ],
            [
                'domain' => 'Section 3: Developing Custom Agents',
                'weight' => 33,
                'question' => 'You are architecting an enterprise multi-agent system on Google Cloud where a primary Planning Agent in Vertex AI delegates inventory checks to an SAP Warehouse Agent running in an external VPC, while connecting to a local PostgreSQL instance for order caching. Which combination of protocols and Google Cloud services is required according to Section 3 of the exam guide?',
                'options' => [
                    ['value' => 'a', 'label' => 'Custom proprietary REST endpoints with basic authentication for all agents and tools.'],
                    ['value' => 'b', 'label' => 'Agent2Agent (A2A) protocol for inter-agent communication registered in Agent Registry, and Model Context Protocol (MCP) servers for database and tool integration.'],
                    ['value' => 'c', 'label' => 'Single monolithic prompt containing the entire database schema and all warehouse logic.'],
                    ['value' => 'd', 'label' => 'Direct SSH socket connections between agent containers without identity verification.'],
                ],
                'correct' => 'b',
                'explanation' => 'Google Cloud custom agent architecture (Section 3) standardizes on two foundational protocols: MCP (Model Context Protocol) for Agent-to-Tool / Agent-to-Database connectivity, and A2A (Agent2Agent) for Agent-to-Agent handoffs. Agent Registry catalogues available agents and capabilities, while Agent Identity enforces access boundaries.',
                'citation' => 'Exam Guide Section 3.2 & 3.3: Integrating domain knowledge & Orchestrating agentic workflows',
                'source_course' => 'Deploy Multi-Agent Architectures (Course 1445) & ADK (Course 1596)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1445',
            ],
            [
                'domain' => 'Section 4: Evaluating & Deploying Workflows',
                'weight' => 22,
                'question' => 'During production staging of a customer-support agent, monitoring telemetry detects recurring latency spikes and occasional runaway tool invocation loops. Which continuous evaluation and mitigation strategy complies with Section 4?',
                'options' => [
                    ['value' => 'a', 'label' => 'Remove all tool definitions to force the model to answer from memory.'],
                    ['value' => 'b', 'label' => 'Implement cycle-detection heuristics with strict turn ceilings in the orchestrator, and establish continuous evaluation pipelines using ADK evalset against a golden dataset.'],
                    ['value' => 'c', 'label' => 'Double the container CPU without profiling tool response times.'],
                    ['value' => 'd', 'label' => 'Rely solely on user thumbs-up/thumbs-down feedback after production rollout.'],
                ],
                'correct' => 'b',
                'explanation' => 'Section 4 requires evaluating agents against golden datasets using ADK evaluation tooling (evalset), detecting reasoning loops and latency bottlenecks, and implementing strict turn limits before deploying to Agent Runtime, Cloud Run, or GKE.',
                'citation' => 'Exam Guide Section 4.1 & 4.2: Evaluating & Deploying Production Workloads',
                'source_course' => 'AgentOps: Operationalize AI Agents on Google Cloud (Course 1782)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1782',
            ],
            [
                'domain' => 'Section 5: Securing & Governing Workflows',
                'weight' => 15,
                'question' => 'An autonomous financial reimbursement agent has permission to approve transactions up to $10,000. For compliance and security, the system must filter prompt injections, redact PII, log all tool API calls, and ensure transactions exceeding $10,000 halt for manager review. Which architecture meets these requirements?',
                'options' => [
                    ['value' => 'a', 'label' => 'Instruct the LLM in the system prompt to never exceed $10,000 and promise not to leak PII.'],
                    ['value' => 'b', 'label' => 'Configure Model Armor for prompt injection sanitization and PII redaction, route tool traffic through Agent Gateway for monitoring and audit logging, and enforce a deterministic Human-in-the-Loop (HITL) gate for high-value thresholds.'],
                    ['value' => 'c', 'label' => 'Store all API keys in environment variables without gateway monitoring.'],
                    ['value' => 'd', 'label' => 'Encrypt the database but allow the agent unrestricted autonomous execution.'],
                ],
                'correct' => 'b',
                'explanation' => 'Enterprise agent governance (Section 5) enforces defense-in-depth: Model Armor sanitizes inputs and redacts sensitive data, Agent Gateway tracks and inspects traffic, and HITL boundaries ensure high-consequence operations require human approval.',
                'citation' => 'Exam Guide Section 5.1 & 5.2: Securing and governing agentic workflows',
                'source_course' => 'Understand Google Cloud Agents: Security, access control, and deployment (Course 1504)',
                'source_url' => 'https://www.skills.google/paths/4525/course_templates/1504',
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
                        'source_course' => $q['source_course'],
                        'source_url' => $q['source_url'],
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
                        'blueprint_weight' => "{$q['weight']}%",
                        'source_course' => $q['source_course'],
                        'source_url' => $q['source_url'],
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
