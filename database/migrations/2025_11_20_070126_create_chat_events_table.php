<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Nov 2025 11:31:19 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('chat_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('chat_session_id')->index();
            $table->foreign('chat_session_id')
                ->references('id')
                ->on('chat_sessions')->nullOnDelete();

            $table->string('event_type')->index()->nullable();
            $table->string('actor_type')->index()->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->index(['actor_type', 'actor_id'], 'chat_events_actor_idx');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_events');
    }
};
