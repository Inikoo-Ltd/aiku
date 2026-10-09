<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('agent_order_reference')->nullable();
            $table->index(['organisation_id', 'agent_id', 'agent_order_reference']);
        });

        DB::statement("update purchase_orders set agent_order_reference = data->'split_from'->>'reference' where data->'split_from' is not null and agent_order_reference is null");
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'agent_id', 'agent_order_reference']);
            $table->dropColumn('agent_order_reference');
        });
    }
};
