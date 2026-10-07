<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->timestampTz('left_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
