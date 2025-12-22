<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Dec 2025 20:35:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('organisation_catalogue_stats', 'number_shops_type_faire')) {
                $table->unsignedSmallInteger('number_shops_type_faire')->default(0);
            }
            if (! Schema::hasColumn('organisation_catalogue_stats', 'number_shops_type_wix')) {
                $table->unsignedSmallInteger('number_shops_type_wix')->default(0);
            }
            if (! Schema::hasColumn('organisation_catalogue_stats', 'number_current_shops_type_faire')) {
                $table->unsignedSmallInteger('number_current_shops_type_faire')->default(0);
            }
            if (! Schema::hasColumn('organisation_catalogue_stats', 'number_current_shops_type_wix')) {
                $table->unsignedSmallInteger('number_current_shops_type_wix')->default(0);
            }
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('group_catalogue_stats', 'number_shops_type_faire')) {
                $table->unsignedSmallInteger('number_shops_type_faire')->default(0);
            }
            if (! Schema::hasColumn('group_catalogue_stats', 'number_shops_type_wix')) {
                $table->unsignedSmallInteger('number_shops_type_wix')->default(0);
            }
            if (! Schema::hasColumn('group_catalogue_stats', 'number_current_shops_type_faire')) {
                $table->unsignedSmallInteger('number_current_shops_type_faire')->default(0);
            }
            if (! Schema::hasColumn('group_catalogue_stats', 'number_current_shops_type_wix')) {
                $table->unsignedSmallInteger('number_current_shops_type_wix')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'number_shops_type_faire',
                'number_shops_type_wix',
                'number_current_shops_type_faire',
                'number_current_shops_type_wix',
            ], function ($column) {
                return Schema::hasColumn('organisation_catalogue_stats', $column);
            });

            if ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'number_shops_type_faire',
                'number_shops_type_wix',
                'number_current_shops_type_faire',
                'number_current_shops_type_wix',
            ], function ($column) {
                return Schema::hasColumn('group_catalogue_stats', $column);
            });

            if ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
