<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\McpServerService;
use Illuminate\Console\Command;

class McpServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve {--user= : Email of the FlowForm user this MCP session acts as}';

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
        $user = User::where('email', (string) $this->option('user'))->first();
        if (! $user) {
            $this->error('Pass --user=<email> of an existing FlowForm user.');

            return Command::FAILURE;
        }

        while (($line = fgets(STDIN)) !== false) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            $response = $mcpService->handleLocalMessage($trimmed, $user);
            if ($response !== null) {
                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES)."\n");
                fflush(STDOUT);
            }
        }

        return Command::SUCCESS;
    }
}
