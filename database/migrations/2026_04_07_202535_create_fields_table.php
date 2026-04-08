<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_type_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('config')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
