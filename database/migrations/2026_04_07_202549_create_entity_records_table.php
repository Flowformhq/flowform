<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('entity_records')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_records');
    }
};
