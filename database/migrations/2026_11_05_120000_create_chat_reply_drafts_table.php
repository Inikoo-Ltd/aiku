<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 19:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_reply_drafts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chat_session_id');
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('chat_agent_id')->index();
            $table->foreign('chat_agent_id')->references('id')->on('chat_agents')->cascadeOnDelete();
            $table->text('text');
            $table->timestampsTz();
            $table->unique(['chat_session_id', 'chat_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reply_drafts');
    }
};
