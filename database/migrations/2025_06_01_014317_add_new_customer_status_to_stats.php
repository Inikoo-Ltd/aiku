<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:43:25 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'group_fulfilment_stats',
            'warehouse_stats',
            'organisation_fulfilment_stats',
            'fulfilment_stats',

            'poll_option_stats',
            'organisation_crm_stats',
            'group_crm_stats',
            'shop_crm_stats',
            'poll_stats',


        ];
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'number_customers_status_pre_registration')) {
                    $table->unsignedInteger('number_customers_status_pre_registration')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'group_fulfilment_stats',
            'warehouse_stats',
            'organisation_fulfilment_stats',
            'fulfilment_stats',

            'poll_option_stats',
            'organisation_crm_stats',
            'group_crm_stats',
            'shop_crm_stats',
            'poll_stats',
        ];
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'number_customers_status_pre_registration')) {
                    $table->dropColumn('number_customers_status_pre_registration');
                }
            });
        }
    }
};
