<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('mail_settings')
            ->whereIn('driver', ['ses', 'resend', 'postmark'])
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $message = "Deactivated {$affected} unsupported API mail_settings row(s) (ses/resend/postmark); falling back to .env mail until reconfigured as SMTP.";
            Log::warning($message);

            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                (new ConsoleOutput)->writeln("<comment>Warning: {$message}</comment>");
            }
        }
    }

    public function down(): void
    {
        // Legacy API drivers are permanently unsupported; no automatic re-activation.
    }
};
