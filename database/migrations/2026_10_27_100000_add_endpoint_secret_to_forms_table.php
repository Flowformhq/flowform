<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->string('endpoint_secret_hash', 64)->nullable()->after('captcha_site_key');
            $table->boolean('endpoint_secret_required')->default(false)->after('endpoint_secret_hash');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn(['endpoint_secret_hash', 'endpoint_secret_required']);
        });
    }
};
