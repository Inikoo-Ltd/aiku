<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_assignments', function (Blueprint $table) {

            $table->id();
            $table->unsignedInteger('session_id'); // Foreign key to chat_sessions
            $table->unsignedInteger('agent_id')->nullable(); // Foreign key to agents (bisa null)
            $table->enum('status', ['pending', 'active', 'resolved', 'rejected'])->default('pending');
            $table->enum('assigned_by', ['system', 'user', 'agent'])->default('system');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('note', 500)->nullable();

            // Foreign key constraints
            $table->foreign('session_id')
                  ->references('id')
                  ->on('chat_sessions')
                  ->onDelete('cascade');

            $table->foreign('agent_id')
                  ->references('id')
                  ->on('agents')
                  ->onDelete('set null');


            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_assignments');
    }
};
