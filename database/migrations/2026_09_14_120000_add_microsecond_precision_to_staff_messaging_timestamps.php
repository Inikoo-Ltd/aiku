<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $this->setPrecision(6);
    }

    public function down(): void
    {
        $this->setPrecision(0);
    }

    private function setPrecision(int $precision): void
    {
        Schema::table('staff_messages', function (Blueprint $table) use ($precision) {
            $table->timestampTz('created_at', $precision)->nullable()->change();
        });

        Schema::table('staff_conversation_participants', function (Blueprint $table) use ($precision) {
            $table->timestampTz('last_read_at', $precision)->nullable()->change();
        });

        Schema::table('staff_conversations', function (Blueprint $table) use ($precision) {
            $table->timestampTz('last_message_at', $precision)->nullable()->change();
        });
    }
};
