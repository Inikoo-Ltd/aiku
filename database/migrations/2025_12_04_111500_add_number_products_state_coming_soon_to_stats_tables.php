<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 11:16:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'collection_stats',
        'collection_category_stats',
        'product_category_stats',
        'master_product_category_stats',
        'shop_stats',
        'organisation_catalogue_stats',
        'group_catalogue_stats',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, 'number_products_status_coming_soon')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedInteger('number_products_status_coming_soon')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'number_products_status_coming_soon')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['number_products_status_coming_soon']);
                });
            }
        }
    }
};
