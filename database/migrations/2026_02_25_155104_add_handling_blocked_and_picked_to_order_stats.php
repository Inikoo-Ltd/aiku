<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 15:51:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('order_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_item_transactions_state_handling_blocked')->default(0);
            $table->unsignedSmallInteger('number_item_transactions_state_picked')->default(0);
            $table->unsignedSmallInteger('number_item_transactions_state_packing')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_item_transactions_state_handling_blocked',
                'number_item_transactions_state_picked',
                'number_item_transactions_state_packing',
            ]);
        });
    }
};
