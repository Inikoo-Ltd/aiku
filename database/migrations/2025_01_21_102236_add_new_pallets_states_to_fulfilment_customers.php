<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jan 2025 18:22:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        $tables = ['fulfilment_customers', 'fulfilment_stats', 'organisation_fulfilment_stats', 'group_fulfilment_stats', 'warehouse_stats'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'number_pallets_state_request_return')) {
                    $table->renameColumn('number_pallets_state_request_return', 'number_pallets_state_request_return_in_process');
                }

                if (! Schema::hasColumn($tableName, 'number_pallets_state_request_return_submitted')) {
                    $table->unsignedInteger('number_pallets_state_request_return_submitted')->default(0);
                }

                if (! Schema::hasColumn($tableName, 'number_pallets_state_request_return_confirmed')) {
                    $table->unsignedInteger('number_pallets_state_request_return_confirmed')->default(0);
                }
            });
        }

    }

    public function down(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('organisation_fulfilment_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('group_fulfilment_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });

        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->renameColumn('number_pallets_state_request_return_in_process', 'number_pallets_state_request_return');
            $table->dropColumn('number_pallets_state_request_return_submitted');
            $table->dropColumn('number_pallets_state_request_return_confirmed');
        });
    }
};
