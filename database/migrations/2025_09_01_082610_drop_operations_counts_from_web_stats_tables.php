<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sept 2025 09:10:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // group_web_stats
        if (Schema::hasTable('group_web_stats')) {
            Schema::table('group_web_stats', function (Blueprint $table) {
                if (Schema::hasColumn('group_web_stats', 'number_webpages_type_operations')) {
                    $table->dropColumn('number_webpages_type_operations');
                }
                if (Schema::hasColumn('group_web_stats', 'number_child_webpages_type_operations')) {
                    $table->dropColumn('number_child_webpages_type_operations');
                }
            });
        }

        // organisation_web_stats
        if (Schema::hasTable('organisation_web_stats')) {
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (Schema::hasColumn('organisation_web_stats', 'number_webpages_type_operations')) {
                    $table->dropColumn('number_webpages_type_operations');
                }
                if (Schema::hasColumn('organisation_web_stats', 'number_child_webpages_type_operations')) {
                    $table->dropColumn('number_child_webpages_type_operations');
                }
            });
        }

        // website_stats
        if (Schema::hasTable('website_stats')) {
            Schema::table('website_stats', function (Blueprint $table) {
                if (Schema::hasColumn('website_stats', 'number_webpages_type_operations')) {
                    $table->dropColumn('number_webpages_type_operations');
                }
                if (Schema::hasColumn('website_stats', 'number_child_webpages_type_operations')) {
                    $table->dropColumn('number_child_webpages_type_operations');
                }
            });
        }
    }

    public function down(): void
    {
        // Column types per HasWebStats: number_webpages_type_* and number_child_webpages_type_* are unsignedSmallInteger
        if (Schema::hasTable('group_web_stats')) {
            Schema::table('group_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_web_stats', 'number_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_webpages_type_operations')->default(0);
                }
                if (!Schema::hasColumn('group_web_stats', 'number_child_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_operations')->default(0);
                }
            });
        }

        if (Schema::hasTable('organisation_web_stats')) {
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('organisation_web_stats', 'number_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_webpages_type_operations')->default(0);
                }
                if (!Schema::hasColumn('organisation_web_stats', 'number_child_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_operations')->default(0);
                }
            });
        }

        if (Schema::hasTable('website_stats')) {
            Schema::table('website_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('website_stats', 'number_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_webpages_type_operations')->default(0);
                }
                if (!Schema::hasColumn('website_stats', 'number_child_webpages_type_operations')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_operations')->default(0);
                }
            });
        }
    }
};
