<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 02:22:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {

        $tables = [
            'product_category_time_series_records',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'type')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->char('type', 1)->index();
                    $table->char('frequency', 1)->index();
                });
            }
        }

        $tables = [
            'master_asset_time_series_records',
            'asset_time_series_records',
            'collection_time_series_records',
            'website_time_series_records',
            'shop_time_series_records',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'type')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->char('frequency', 1)->index();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'product_category_time_series_records',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'type')) {
                        $table->dropColumn('type');
                    }
                    if (Schema::hasColumn($table->getTable(), 'frequency')) {
                        $table->dropColumn('frequency');
                    }
                });
            }
        }

        $tables = [
            'master_asset_time_series_records',
            'asset_time_series_records',
            'collection_time_series_records',
            'website_time_series_records',
            'shop_time_series_records',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'frequency')) {
                        $table->dropColumn('frequency');
                    }
                });
            }
        }
    }
};
