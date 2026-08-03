<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('clocking_machines', function (Blueprint $table) {
            $table->string('kiosk_token', 64)->nullable()->unique()->after('qr_code');
        });
    }


    public function down(): void
    {
        Schema::table('clocking_machines', function (Blueprint $table) {
            $table->dropColumn('kiosk_token');
        });
    }
};
