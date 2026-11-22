<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Step;
use App\Services\McpServerService;

test('MCP server responds to initialize handshake', function () {
    $service = new McpServerService();

    $response = $service->handleMessage([
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
    $service = new McpServerService();

    $response = $service->handleMessage([
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
    $service = new McpServerService();

    $response = $service->handleMessage([
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
        'slug' => 'ai-generated-intake',
        'is_encrypted' => true,
    ]);
});

test('MCP server submit_form tool executes runtime submission', function () {
    $form = Form::create([
        'name' => 'Agent Booking',
        'slug' => 'agent-booking',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);

    $service = new McpServerService();

    $response = $service->handleMessage([
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
