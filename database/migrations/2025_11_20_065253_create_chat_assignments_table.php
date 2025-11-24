<?php

use App\Enums\CRM\Livechat\ChatAssigmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chat_session_id')->index()->nullable();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->nullOnDelete();

            $table->unsignedInteger('chat_agent_id')->index()->nullable();
            $table->foreign('chat_agent_id')->references('id')->on('chat_agents')->nullOnDelete();

            $table->string('status')->index()->default(ChatAssignmentStatusEnum::PENDING->value);
            $table->string('assigned_by')->index()->default(ChatAssigmentAssignedByEnum::SYSTEM->value);
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
