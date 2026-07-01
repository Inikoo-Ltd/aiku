<?php

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_knowledge_sources', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('chat_automation_id');
            $table->foreign('chat_automation_id')->references('id')->on('chat_automations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('knowledge_node_id')->index();
            $table->string('source_id')->index();
            $table->string('type')->default(ChatKnowledgeSourceTypeEnum::TEXT->value);
            $table->string('name')->nullable();
            $table->longText('content')->nullable();
            $table->string('status')->default(ChatKnowledgeSourceStatusEnum::PENDING->value);
            $table->string('content_hash')->nullable();
            $table->unsignedInteger('tokens')->nullable();

            $table->timestampsTz();

            $table->unique(['chat_automation_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_knowledge_sources');
    }
};
