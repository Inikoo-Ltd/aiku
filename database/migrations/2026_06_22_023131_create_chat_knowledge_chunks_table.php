<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_knowledge_chunks', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('chat_automation_id');
            $table->foreign('chat_automation_id')->references('id')->on('chat_automations')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedBigInteger('chat_knowledge_source_id')->nullable();
            $table->foreign('chat_knowledge_source_id')->references('id')->on('chat_knowledge_sources')->onUpdate('cascade')->onDelete('cascade');

            $table->string('knowledge_node_id')->index();
            $table->string('guid')->index();
            $table->integer('section_number')->nullable();
            $table->longText('content')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->vector('embedding_384', 384)->nullable();
            $table->vector('embedding_768', 768)->nullable();
            $table->vector('embedding_1024', 1024)->nullable();
            $table->vector('embedding_1536', 1536)->nullable();
            $table->vector('embedding_2048', 2048)->nullable();
            $table->vector('embedding_3072', 3072)->nullable();
            $table->vector('embedding_4096', 4096)->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_knowledge_chunks');
    }
};
