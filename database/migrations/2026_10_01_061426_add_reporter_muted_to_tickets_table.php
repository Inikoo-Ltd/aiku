<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The reporter can mute a ticket they raised so its status notifications (sound, mini-modal,
     * email, badge) stay quiet for them, without affecting anyone else watching the same ticket.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('reporter_muted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reporter_muted');
        });
    }
};
