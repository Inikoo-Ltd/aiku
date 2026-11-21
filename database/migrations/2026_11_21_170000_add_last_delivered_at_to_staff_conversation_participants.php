<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->timestampTz('last_delivered_at', 6)->nullable()->comment('newest moment this person\'s aiku had received the conversation\'s messages');
        });
    }

    public function down(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->dropColumn('last_delivered_at');
        });
    }
};
