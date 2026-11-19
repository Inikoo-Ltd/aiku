<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 03:48:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->boolean('time_lost')->nullable()->index()->comment('staff ignored or discarded the draft (with no [[gap]] left) and then sent a reply Jev judged the same (facts.near_match score 2+): a right draft retyped. Null until scored');
            $table->unsignedInteger('time_lost_seconds')->nullable()->comment('when time_lost: active seconds the agent spent writing that reply (metadata.writing_seconds of the staff message, measured in the inbox since 19 Nov 2026)');
        });

        DB::statement("update chat_ai_drafts set time_lost = ((facts->'near_match'->>'score')::int >= 2 and position('[[' in text) = 0) where change_class in ('own_reply', 'discarded') and facts->'near_match' is not null and facts->>'mode' = 'assistant' and coalesce(facts->>'shadow', 'false') <> 'true'");
        DB::statement("update chat_ai_drafts set time_lost_seconds = (chat_messages.metadata->>'writing_seconds')::int from chat_messages where chat_ai_drafts.time_lost and chat_ai_drafts.chat_session_id is not null and chat_messages.id = chat_ai_drafts.reply_message_id and chat_messages.metadata->>'writing_seconds' is not null");
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn(['time_lost', 'time_lost_seconds']);
        });
    }
};
