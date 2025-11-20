<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('scheduled_task_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->string('task_type');
            $table->string('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->float('duration')->nullable();
            $table->string('status')->default('running');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['task_name', 'scheduled_at']);
            $table->index('started_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_logs');
    }
};
