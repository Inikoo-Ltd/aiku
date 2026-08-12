<?php

use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('shop_has_meta_chat_agents', function (Blueprint $table) {
            $table->smallIncrements('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('set null');

            $table->unsignedSmallInteger('meta_chat_agent_id')->index();
            $table->foreign('meta_chat_agent_id')->references('id')->on('meta_chat_agents')->onUpdate('cascade')->onDelete('cascade');

            $table->unique(
                ['meta_channel_id', 'organisation_id', 'shop_id', 'meta_chat_agent_id'],
                'shop_meta_chat_agents_unique'
            );

            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_has_meta_chat_agents');
    }
};
