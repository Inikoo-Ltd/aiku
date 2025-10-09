<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sept 2025 08:26:50 Malaysia Time, Kuala Lumpur, Malaysia
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
                if (Schema::hasColumn('group_web_stats', 'number_webpages_type_info')) {
                    $table->dropColumn('number_webpages_type_info');
                }
                if (Schema::hasColumn('group_web_stats', 'number_child_webpages_type_info')) {
                    $table->dropColumn('number_child_webpages_type_info');
                }
            });
        }

        // organisation_web_stats
        if (Schema::hasTable('organisation_web_stats')) {
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (Schema::hasColumn('organisation_web_stats', 'number_webpages_type_info')) {
                    $table->dropColumn('number_webpages_type_info');
                }
                if (Schema::hasColumn('organisation_web_stats', 'number_child_webpages_type_info')) {
                    $table->dropColumn('number_child_webpages_type_info');
                }
            });
        }

        // website_stats
        if (Schema::hasTable('website_stats')) {
            Schema::table('website_stats', function (Blueprint $table) {
                if (Schema::hasColumn('website_stats', 'number_webpages_type_info')) {
                    $table->dropColumn('number_webpages_type_info');
                }
                if (Schema::hasColumn('website_stats', 'number_child_webpages_type_info')) {
                    $table->dropColumn('number_child_webpages_type_info');
                }
            });
        }
    }

    public function down(): void
    {
        // Column types per HasWebStats: number_webpages_type_* and number_child_webpages_type_* are unsignedSmallInteger
        if (Schema::hasTable('group_web_stats')) {
            Schema::table('group_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_web_stats', 'number_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_webpages_type_info')->default(0);
                }
                if (!Schema::hasColumn('group_web_stats', 'number_child_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_info')->default(0);
                }
            });
        }

        if (Schema::hasTable('organisation_web_stats')) {
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('organisation_web_stats', 'number_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_webpages_type_info')->default(0);
                }
                if (!Schema::hasColumn('organisation_web_stats', 'number_child_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_info')->default(0);
                }
            });
        }

        if (Schema::hasTable('website_stats')) {
            Schema::table('website_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('website_stats', 'number_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_webpages_type_info')->default(0);
                }
                if (!Schema::hasColumn('website_stats', 'number_child_webpages_type_info')) {
                    $table->unsignedSmallInteger('number_child_webpages_type_info')->default(0);
                }
            });
        }
    }
};
