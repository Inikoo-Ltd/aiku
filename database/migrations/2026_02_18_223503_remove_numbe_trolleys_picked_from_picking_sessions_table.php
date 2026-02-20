<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 06:36:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->dropColumn('numbe_trolleys_picked');
        });
    }

    public function down(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->unsignedInteger('numbe_trolleys_picked')->default(0);
        });
    }
};
