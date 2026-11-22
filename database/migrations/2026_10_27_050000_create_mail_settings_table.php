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
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->default('smtp'); // smtp, ses, resend, postmark, log
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->default(587);
            $table->string('encryption')->nullable()->default('tls'); // tls, ssl, null
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted
            $table->text('api_key')->nullable(); // encrypted
            $table->string('region')->nullable()->default('us-east-1');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
