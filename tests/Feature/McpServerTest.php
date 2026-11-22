<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\McpServerService;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\TransientToken;

test('MCP server responds to initialize handshake', function () {
    $service = new McpServerService;
    $user = User::factory()->create()->withAccessToken(new TransientToken);

    $response = $service->handleMessage(user: $user, input: [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
        ],
    ]);

    expect($response['jsonrpc'])->toEqual('2.0');
    expect($response['id'])->toEqual(1);
    expect($response['result']['protocolVersion'])->toEqual('2024-11-05');
    expect($response['result']['serverInfo']['name'])->toEqual('flowform-mcp');
});

test('MCP server lists all 5 native tools', function () {
    $service = new McpServerService;
    $user = User::factory()->create()->withAccessToken(new TransientToken);

    $response = $service->handleMessage(user: $user, input: [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ]);

    expect($response['jsonrpc'])->toEqual('2.0');
    $toolNames = collect($response['result']['tools'])->pluck('name')->toArray();

    expect($toolNames)->toContain('list_forms');
    expect($toolNames)->toContain('get_form_schema');
    expect($toolNames)->toContain('create_form');
    expect($toolNames)->toContain('submit_form');
    expect($toolNames)->toContain('list_submissions');
});

test('MCP server create_form tool programmatically creates form', function () {
    $service = new McpServerService;
    $user = User::factory()->create()->withAccessToken(new TransientToken);

    $response = $service->handleMessage(user: $user, input: [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_form',
            'arguments' => [
                'name' => 'AI Generated Intake',
                'slug' => 'ai-generated-intake',
                'type' => 'endpoint',
                'is_encrypted' => true,
            ],
        ],
    ]);

    expect($response['id'])->toEqual(3);
    $text = $response['result']['content'][0]['text'];
    $data = json_decode($text, true);

    expect($data['message'])->toEqual('Form created successfully');
    expect($data['form']['slug'])->toEqual('ai-generated-intake');
    expect($data['form']['is_encrypted'])->toBeTrue();

    $this->assertDatabaseHas('forms', [
        'user_id' => $user->id,
        'slug' => 'ai-generated-intake',
        'is_encrypted' => true,
    ]);
});

test('MCP server submit_form tool executes runtime submission', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Agent Booking',
        'slug' => 'agent-booking',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);

    $service = new McpServerService;
    $owner->withAccessToken(new TransientToken);

    $response = $service->handleMessage(user: $owner, input: [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => [
            'name' => 'submit_form',
            'arguments' => [
                'form_slug' => 'agent-booking',
                'data' => [
                    'agent_name' => 'Claude Agent',
                    'task' => 'Book appointment',
                ],
            ],
        ],
    ]);

    expect($response['id'])->toEqual(4);
    $text = $response['result']['content'][0]['text'];
    $data = json_decode($text, true);

    expect($data['success'])->toBeTrue();
    expect($data['form'])->toEqual('agent-booking');
    expect($data['is_encrypted'])->toBeTrue();

    $this->assertDatabaseHas('submissions', [
        'form_id' => $form->id,
        'is_encrypted' => true,
    ]);
});

test('POST /api/v1/mcp endpoint handles HTTP JSON-RPC calls', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/mcp', [
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'initialize',
    ]);

    $response->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 10)
        ->assertJsonPath('result.serverInfo.name', 'flowform-mcp');
});

function mcpToolCall(string $tool, array $arguments = []): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]];
}

function mcpToolResult($response): array
{
    return json_decode($response->json('result.content.0.text'), true) ?? [];
}

test('POST /api/v1/mcp rejects unauthenticated requests', function (string $method) {
    $form = Form::create(['name' => 'Private', 'slug' => 'private', 'type' => 'endpoint', 'is_active' => true]);
    Submission::create(['form_id' => $form->id, 'status' => 'completed', 'data' => ['secret' => 'x']]);

    $this->postJson('/api/v1/mcp', mcpToolCall('list_submissions', ['form_slug' => 'private']))->assertUnauthorized();
    $this->post('/api/v1/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method])->assertUnauthorized();
})->with(['initialize', 'tools/list']);

test('MCP tools never expose another user\'s forms or submissions', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceForm = Form::create(['user_id' => $alice->id, 'name' => 'Alice', 'slug' => 'alice-form', 'type' => 'endpoint', 'is_active' => true]);
    Submission::create(['form_id' => $aliceForm->id, 'status' => 'completed', 'data' => ['email' => 'alice@example.com']]);

    Sanctum::actingAs($bob, ['*']);

    expect(mcpToolResult($this->postJson('/api/v1/mcp', mcpToolCall('list_forms'))))->toBe([]);

    foreach ([
        mcpToolCall('list_submissions', ['form_slug' => 'alice-form']),
        mcpToolCall('list_submissions', ['form_slug' => $aliceForm->uuid]),
        mcpToolCall('get_form_schema', ['identifier' => 'alice-form']),
        mcpToolCall('submit_form', ['form_slug' => 'alice-form', 'data' => ['x' => 1]]),
    ] as $call) {
        $response = $this->postJson('/api/v1/mcp', $call)->assertOk();
        expect($response->json('result.isError'))->toBeTrue();
        expect($response->json('result.content.0.text'))->not->toContain('alice@example.com');
    }

    expect(Submission::where('form_id', $aliceForm->id)->count())->toBe(1);
});

test('MCP owner can list own submissions and admin can see all forms', function () {
    $alice = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::create(['user_id' => $alice->id, 'name' => 'Alice', 'slug' => 'alice-form', 'type' => 'endpoint', 'is_active' => true]);
    Submission::create(['form_id' => $form->id, 'status' => 'completed', 'data' => ['email' => 'alice@example.com']]);

    Sanctum::actingAs($alice, ['*']);
    expect(mcpToolResult($this->postJson('/api/v1/mcp', mcpToolCall('list_submissions', ['form_slug' => 'alice-form']))))->toHaveCount(1);

    Sanctum::actingAs($admin, ['*']);
    expect(collect(mcpToolResult($this->postJson('/api/v1/mcp', mcpToolCall('list_forms'))))->pluck('slug')->all())->toContain('alice-form');
});

test('MCP submit_form rejects inactive forms', function () {
    $alice = User::factory()->create();
    Form::create(['user_id' => $alice->id, 'name' => 'Off', 'slug' => 'off-form', 'type' => 'endpoint', 'is_active' => false]);

    Sanctum::actingAs($alice, ['*']);
    $response = $this->postJson('/api/v1/mcp', mcpToolCall('submit_form', ['form_slug' => 'off-form', 'data' => ['x' => 1]]));

    expect($response->json('result.isError'))->toBeTrue();
    expect(Submission::count())->toBe(0);
});

test('MCP tools require the matching token ability', function () {
    $alice = User::factory()->create();
    Form::create(['user_id' => $alice->id, 'name' => 'Alice', 'slug' => 'alice-form', 'type' => 'endpoint', 'is_active' => true]);

    Sanctum::actingAs($alice, ['forms:read']);

    expect($this->postJson('/api/v1/mcp', mcpToolCall('list_forms'))->json('result.isError'))->toBeNull();

    $denied = $this->postJson('/api/v1/mcp', mcpToolCall('list_submissions', ['form_slug' => 'alice-form']));
    expect($denied->json('result.isError'))->toBeTrue();
    expect($denied->json('result.content.0.text'))->toContain('submissions:read');

    expect($this->postJson('/api/v1/mcp', mcpToolCall('create_form', ['name' => 'Nope']))->json('result.isError'))->toBeTrue();
    $this->assertDatabaseMissing('forms', ['name' => 'Nope']);
});

test('mcp:serve requires an existing user', function () {
    $this->artisan('mcp:serve')->assertFailed();
    $this->artisan('mcp:serve', ['--user' => 'nobody@example.com'])->assertFailed();
});
