<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 12:18:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTimeTz('picked_at')->nullable();
            $table->dateTimeTz('packing_at')->nullable();

        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dateTimeTz('picked_at')->nullable();
            $table->dateTimeTz('packing_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('picked_at');
            $table->dropColumn('packing_at');
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('picked_at');
            $table->dropColumn('packing_at');
        });
    }
};
