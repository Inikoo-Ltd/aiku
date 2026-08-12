<?php

use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_chat_assignments', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('meta_chat_session_id')->index()->nullable();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->nullOnDelete();

            $table->unsignedSmallInteger('meta_chat_agent_id')->index()->nullable();
            $table->foreign('meta_chat_agent_id')->references('id')->on('meta_chat_agents')->nullOnDelete();

            $table->string('status')->index()->default(ChatAssignmentStatusEnum::PENDING->value);
            $table->string('assigned_by')->index()->default(ChatAssignmentAssignedByEnum::SYSTEM->value);
            $table->timestampTz('assigned_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->text('note')->nullable();

            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_assignments');
    }
};
