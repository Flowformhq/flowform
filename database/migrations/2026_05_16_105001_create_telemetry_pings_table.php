<?php

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
        Schema::create('telemetry_pings', function (Blueprint $table) {
            $table->id();
            $table->uuid('install_id');
            $table->string('version')->nullable();
            $table->string('response_volume_bucket')->nullable();
            $table->string('php_version')->nullable();
            $table->string('database_driver')->nullable();
            $table->string('country')->nullable();
            $table->json('enabled_integrations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_pings');
    }
};
