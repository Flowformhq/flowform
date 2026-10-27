<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\McpServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpController extends Controller
{
    /**
     * Handle incoming Model Context Protocol (MCP) JSON-RPC request.
     */
    public function handle(Request $request, McpServerService $mcpService): JsonResponse
    {
        $response = $mcpService->handleMessage($request->all());

        if ($response === null) {
            return response()->json(['jsonrpc' => '2.0', 'result' => null]);
        }

        return response()->json($response);
    }
}
