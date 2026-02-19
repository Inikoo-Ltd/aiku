<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 13:05:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'organisation_ordering_stats',
            'shop_ordering_stats',
            'asset_ordering_stats',
            'group_ordering_stats',
            'collection_ordering_stats',
            'master_shop_ordering_stats',
            'master_asset_ordering_stats',
            'variant_sales_ordering_stats',
            'master_variant_ordering_stats',
            'master_collection_ordering_stats',
            'master_product_category_ordering_stats',
            'product_category_ordering_stats',
            'customer_stats',
            'customer_client_stats',
            'platform_stats'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'number_delivery_notes_type_picked')) {
                    $table->unsignedInteger('number_delivery_notes_type_picked')->default(0);
                }
                if (! Schema::hasColumn($tableName, 'number_delivery_notes_type_packing')) {
                    $table->unsignedInteger('number_delivery_notes_type_packing')->default(0);
                }
                if (! Schema::hasColumn($tableName, 'number_delivery_notes_cancelled_at_state_picked')) {
                    $table->unsignedInteger('number_delivery_notes_cancelled_at_state_picked')->default(0);
                }
                if (! Schema::hasColumn($tableName, 'number_delivery_notes_cancelled_at_state_packed')) {
                    $table->unsignedInteger('number_delivery_notes_cancelled_at_state_packed')->default(0);
                }
                if (! Schema::hasColumn($tableName, 'number_orders_type_picked')) {
                    $table->unsignedInteger('number_orders_type_picked')->default(0);
                }
                if (! Schema::hasColumn($tableName, 'number_orders_type_packing')) {
                    $table->unsignedInteger('number_orders_type_packing')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'organisation_ordering_stats',
            'shop_ordering_stats',
            'asset_ordering_stats',
            'group_ordering_stats',
            'collection_ordering_stats',
            'master_shop_ordering_stats',
            'master_asset_ordering_stats',
            'variant_sales_ordering_stats',
            'master_variant_ordering_stats',
            'master_collection_ordering_stats',
            'master_product_category_ordering_stats',
            'product_category_ordering_stats',
            'customer_stats',
            'customer_client_stats',
            'platform_stats'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'number_delivery_notes_type_picked',
                    'number_delivery_notes_type_packing',
                    'number_delivery_notes_cancelled_at_state_picked',
                    'number_delivery_notes_cancelled_at_state_packed',
                    'number_orders_type_picked',
                    'number_orders_type_packing',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
