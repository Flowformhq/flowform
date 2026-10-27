<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('allowed_domains');
            $table->json('encrypted_fields')->nullable()->after('is_encrypted');
            $table->string('webhook_url')->nullable()->after('encrypted_fields');
            $table->string('webhook_secret')->nullable()->after('webhook_url');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'encrypted_fields', 'webhook_url', 'webhook_secret']);
        });
    }
};
