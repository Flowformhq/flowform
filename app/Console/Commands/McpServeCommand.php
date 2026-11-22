<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\McpServerService;
use Illuminate\Console\Command;

class McpServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the FlowForm Model Context Protocol (MCP) server over standard input/output';

    /**
     * Execute the console command.
     */
    public function handle(McpServerService $mcpService): int
    {
        while (($line = fgets(STDIN)) !== false) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            $response = $mcpService->handleMessage($trimmed);
            if ($response !== null) {
                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES) . "\n");
                fflush(STDOUT);
            }
        }

        return Command::SUCCESS;
    }
}
