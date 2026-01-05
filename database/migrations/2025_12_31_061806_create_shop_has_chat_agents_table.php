<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shop_has_chat_agents', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('set null');

            $table->unsignedSmallInteger('chat_agent_id')->index();
            $table->foreign('chat_agent_id')->references('id')->on('chat_agents')->onUpdate('cascade')->onDelete('cascade');

            $table->unique(
                ['organisation_id', 'shop_id', 'chat_agent_id'],
                'shop_chat_agents_unique'
            );

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_has_chat_agents');
    }
};
