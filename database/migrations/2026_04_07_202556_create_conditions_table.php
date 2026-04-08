<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('operator');
            $table->string('value')->nullable();
            $table->string('action')->default('show');
            $table->timestamps();

            $table->index('depends_on_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conditions');
    }
};
