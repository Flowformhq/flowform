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
            $table->string('type')->default('wizard')->after('slug');
            $table->string('notification_email')->nullable()->after('description');
            $table->string('redirect_url')->nullable()->after('notification_email');
            $table->index('type');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->json('data')->nullable()->after('current_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('data');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'notification_email', 'redirect_url']);
        });
    }
};
