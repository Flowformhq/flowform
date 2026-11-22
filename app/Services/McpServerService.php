<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DispatchFormWebhook;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Sanctum\TransientToken;

class McpServerService
{
    public const PROTOCOL_VERSION = '2024-11-05';

    public const SERVER_NAME = 'flowform-mcp';

    public const SERVER_VERSION = '1.0.0';

    /**
     * @var array<string, string>
     */
    protected const TOOL_ABILITIES = [
        'list_forms' => 'forms:read',
        'get_form_schema' => 'forms:read',
        'create_form' => 'forms:write',
        'submit_form' => 'submissions:write',
        'list_submissions' => 'submissions:read',
    ];

    /**
     * Handle a message from a trusted local session (stdio), granting every token ability.
     *
     * @return array<string, mixed>|null
     */
    public function handleLocalMessage(string $input, User $user): ?array
    {
        // @phpstan-ignore argument.type (TransientToken is Sanctum's first-party token for trusted non-token sessions)
        return $this->handleMessage($input, $user->withAccessToken(new TransientToken));
    }

    /**
     * Handle an incoming MCP JSON-RPC message (as array or JSON string) and return the response array.
     */
    public function handleMessage(string|array $input, User $user): ?array
    {
        $payload = is_string($input) ? json_decode($input, true) : $input;
        if (! is_array($payload) || ! isset($payload['jsonrpc'])) {
            return [
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Invalid Request: missing jsonrpc version'],
            ];
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? '';
        $params = $payload['params'] ?? [];

        // Notifications (no id)
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->handleInitialize($id),
            'ping' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => new \stdClass],
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params, $user),
            default => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => "Method not found: {$method}"],
            ],
        };
    }

    protected function handleInitialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => [
                    'tools' => ['listChanged' => false],
                ],
                'serverInfo' => [
                    'name' => self::SERVER_NAME,
                    'version' => self::SERVER_VERSION,
                ],
            ],
        ];
    }

    protected function handleToolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => [
                    [
                        'name' => 'list_forms',
                        'description' => 'List active FlowForm endpoints and multi-step wizards with their configuration and status.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'limit' => [
                                    'type' => 'integer',
                                    'description' => 'Maximum number of forms to retrieve (default: 20)',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_form_schema',
                        'description' => 'Retrieve the full step-by-step field schema, validation rules, and server-side conditional logic for a form.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'identifier' => [
                                    'type' => 'string',
                                    'description' => 'The form UUID or slug to inspect.',
                                ],
                            ],
                            'required' => ['identifier'],
                        ],
                    ],
                    [
                        'name' => 'create_form',
                        'description' => 'Programmatically create a new FlowForm endpoint or multi-step wizard.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Form title'],
                                'slug' => ['type' => 'string', 'description' => 'URL slug (optional)'],
                                'type' => ['type' => 'string', 'enum' => ['endpoint', 'wizard'], 'description' => 'Form mode: endpoint (HTML Mode 1) or wizard (Multi-Step Mode 2)'],
                                'is_encrypted' => ['type' => 'boolean', 'description' => 'Enable field-level AES-256 encryption at rest'],
                                'webhook_url' => ['type' => 'string', 'description' => 'Outbound webhook URL for submissions'],
                                'steps' => [
                                    'type' => 'array',
                                    'description' => 'Array of step titles for multi-step forms',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => ['name'],
                        ],
                    ],
                    [
                        'name' => 'submit_form',
                        'description' => 'Submit data payload to a FlowForm endpoint at runtime, triggering encryption, validation, and webhooks.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'form_slug' => ['type' => 'string', 'description' => 'The slug of the target form'],
                                'data' => [
                                    'type' => 'object',
                                    'description' => 'Key-value map of form field submissions',
                                ],
                            ],
                            'required' => ['form_slug', 'data'],
                        ],
                    ],
                    [
                        'name' => 'list_submissions',
                        'description' => 'Retrieve recent submission records for a specific form.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'form_slug' => ['type' => 'string', 'description' => 'Target form slug'],
                                'limit' => ['type' => 'integer', 'description' => 'Number of submissions to return (default: 10)'],
                            ],
                            'required' => ['form_slug'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function handleToolsCall(mixed $id, array $params, User $user): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        try {
            $ability = self::TOOL_ABILITIES[$name] ?? null;
            if ($ability !== null && ! $user->tokenCan($ability)) {
                throw new \InvalidArgumentException("Token is missing the '{$ability}' ability.");
            }

            $result = match ($name) {
                'list_forms' => $this->toolListForms($args, $user),
                'get_form_schema' => $this->toolGetFormSchema($args, $user),
                'create_form' => $this->toolCreateForm($args, $user),
                'submit_form' => $this->toolSubmitForm($args, $user),
                'list_submissions' => $this->toolListSubmissions($args, $user),
                default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT),
                        ],
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Error executing tool {$name}: {$e->getMessage()}",
                        ],
                    ],
                    'isError' => true,
                ],
            ];
        }
    }

    protected function toolListForms(array $args, User $user): array
    {
        $limit = min((int) ($args['limit'] ?? 20), 50);

        return $this->formsVisibleTo($user)
            ->active()
            ->latest()
            ->limit($limit)
            ->get(['uuid', 'name', 'slug', 'type', 'is_encrypted', 'webhook_url', 'created_at'])
            ->map(fn ($f) => [
                'uuid' => $f->uuid,
                'name' => $f->name,
                'slug' => $f->slug,
                'type' => $f->type,
                'endpoint' => url("/f/{$f->slug}"),
                'is_encrypted' => (bool) $f->is_encrypted,
                'has_webhook' => ! empty($f->webhook_url),
                'created_at' => $f->created_at?->toISOString(),
            ])
            ->toArray();
    }

    protected function toolGetFormSchema(array $args, User $user): array
    {
        $identifier = $args['identifier'] ?? '';
        $form = $this->findVisibleForm($user, $identifier)
            ->with(['steps.fields.fieldType', 'steps.fields.options', 'steps.fields.conditions'])
            ->firstOrFail();

        return [
            'uuid' => $form->uuid,
            'name' => $form->name,
            'slug' => $form->slug,
            'type' => $form->type,
            'endpoint' => url("/f/{$form->slug}"),
            'is_encrypted' => (bool) $form->is_encrypted,
            'steps' => $form->steps->map(fn ($step) => [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'fields' => $step->fields->map(fn ($field) => [
                    'code' => $field->code,
                    'label' => $field->label,
                    'type' => $field->fieldType?->name,
                    'is_required' => (bool) $field->is_required,
                    'options' => $field->options->pluck('label', 'value'),
                    'conditions' => $field->conditions->map(fn ($c) => [
                        'depends_on' => $c->dependsOnField?->code,
                        'operator' => $c->operator,
                        'value' => $c->value,
                        'action' => $c->action,
                    ]),
                ]),
            ])->toArray(),
        ];
    }

    protected function toolCreateForm(array $args, User $user): array
    {
        $name = $args['name'] ?? 'Untitled Form';
        $slug = $args['slug'] ?? Str::slug($name);
        $type = $args['type'] ?? 'endpoint';
        $isEncrypted = (bool) ($args['is_encrypted'] ?? false);
        $webhookUrl = $args['webhook_url'] ?? null;

        $form = Form::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'is_active' => true,
            'is_encrypted' => $isEncrypted,
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookUrl ? 'whsec_'.Str::random(24) : null,
        ]);

        // If wizard mode and steps provided, create steps
        if ($type === 'wizard' && ! empty($args['steps'])) {
            foreach ($args['steps'] as $idx => $stepTitle) {
                Step::create([
                    'form_id' => $form->id,
                    'step_number' => $idx + 1,
                    'title' => (string) $stepTitle,
                ]);
            }
        }

        return [
            'message' => 'Form created successfully',
            'form' => [
                'uuid' => $form->uuid,
                'name' => $form->name,
                'slug' => $form->slug,
                'type' => $form->type,
                'endpoint' => url("/f/{$form->slug}"),
                'is_encrypted' => (bool) $form->is_encrypted,
            ],
        ];
    }

    protected function toolSubmitForm(array $args, User $user): array
    {
        $slug = $args['form_slug'] ?? '';
        $data = $args['data'] ?? [];

        $form = $this->findVisibleForm($user, $slug)
            ->active()
            ->firstOrFail();

        $submission = new Submission;
        $submission->form_id = $form->id;
        $submission->user_id = $user->id;
        $submission->status = 'completed';
        $submission->is_encrypted = (bool) $form->is_encrypted;
        $submission->data = $data;
        $submission->meta = [
            'source' => 'mcp-agent',
            'ip' => '127.0.0.1',
            'user_agent' => 'FlowForm-MCP/1.0',
        ];
        $submission->save();

        // Dispatch webhook if configured
        if (! empty($form->webhook_url)) {
            try {
                DispatchFormWebhook::dispatch($form, $submission, $data);
            } catch (\Throwable $e) {
                // Ignore queue exceptions in test
            }
        }

        return [
            'success' => true,
            'submission_id' => $submission->uuid,
            'status' => 'completed',
            'is_encrypted' => (bool) $submission->is_encrypted,
            'form' => $form->slug,
        ];
    }

    protected function toolListSubmissions(array $args, User $user): array
    {
        $slug = $args['form_slug'] ?? '';
        $limit = min((int) ($args['limit'] ?? 10), 30);

        $form = $this->findVisibleForm($user, $slug)->firstOrFail();

        return $form->submissions()
            ->latest()
            ->limit($limit)
            ->get(['uuid', 'status', 'is_encrypted', 'data', 'created_at'])
            ->map(fn ($s) => [
                'uuid' => $s->uuid,
                'status' => $s->status,
                'is_encrypted' => (bool) $s->is_encrypted,
                'data' => $s->data,
                'created_at' => $s->created_at?->toISOString(),
            ])
            ->toArray();
    }

    /**
     * @return Builder<Form>
     */
    protected function formsVisibleTo(User $user): Builder
    {
        return Form::query()->when(! $user->is_admin, fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * @return Builder<Form>
     */
    protected function findVisibleForm(User $user, string $identifier): Builder
    {
        return $this->formsVisibleTo($user)
            ->where(fn (Builder $query) => $query->where('uuid', $identifier)->orWhere('slug', $identifier));
    }
}
