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
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('model_id'); // e.g., "claude-3-5-sonnet-20241022"
            $table->string('display_name');
            $table->integer('context_window')->nullable();
            $table->boolean('supports_vision')->default(false);
            $table->boolean('supports_streaming')->default(true);
            $table->decimal('pricing_input', 10, 6)->nullable(); // per 1M tokens
            $table->decimal('pricing_output', 10, 6)->nullable(); // per 1M tokens
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'model_id']);
            $table->index(['provider_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
