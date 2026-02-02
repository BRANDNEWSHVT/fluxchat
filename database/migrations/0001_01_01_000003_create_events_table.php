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
        if (!file_exists(database_path('events.sqlite'))) {
            touch(database_path('events.sqlite'));
        }

        Schema::connection('sqlite_events')->create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->index();
            $table->string('entity_id')->index();
            $table->string('event_type')->index();
            $table->text('payload')->nullable();
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlite_jobs')->dropIfExists('jobs');
        Schema::connection('sqlite_jobs')->dropIfExists('job_batches');
        Schema::connection('sqlite_jobs')->dropIfExists('failed_jobs');
    }
};
