<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_assignments', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('chat_session_id')->index()->nullable();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->nullOnDelete();

            $table->unsignedInteger('chat_agent_id')->index()->nullable();
            $table->foreign('chat_agent_id')->references('id')->on('chat_agents')->nullOnDelete();

            $table->enum('status', ['pending', 'active', 'resolved', 'rejected'])->default('pending');
            $table->enum('assigned_by', ['system', 'user', 'agent'])->default('system');
            $table->timestamp('assigned_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('note')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_assignments');
    }
};