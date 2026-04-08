<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->json('validation_rules')->nullable()->after('meta');
            $table->boolean('is_visible')->default(true)->after('validation_rules');
        });
    }

    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn(['validation_rules', 'is_visible']);
        });
    }
};
