<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            delete from chat_turn_readings duplicate
            using chat_turn_readings kept
            where duplicate.customer_message_id = kept.customer_message_id
              and duplicate.chat_session_id is not distinct from kept.chat_session_id
              and duplicate.meta_chat_session_id is not distinct from kept.meta_chat_session_id
              and duplicate.id > kept.id
        ');

        Schema::table('chat_turn_readings', function (Blueprint $table) {
            $table->unique(['chat_session_id', 'customer_message_id']);
            $table->unique(['meta_chat_session_id', 'customer_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_turn_readings', function (Blueprint $table) {
            $table->dropUnique(['chat_session_id', 'customer_message_id']);
            $table->dropUnique(['meta_chat_session_id', 'customer_message_id']);
        });
    }
};
