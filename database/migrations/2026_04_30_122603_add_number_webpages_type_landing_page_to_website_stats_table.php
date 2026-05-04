<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Apr 2026 18:16:54 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('website_stats', 'number_webpages_type_landing_page')) {
                $table->unsignedSmallInteger('number_webpages_type_landing_page')->default(0);
            }
            if (!Schema::hasColumn('website_stats', 'number_webpages_sub_type_landing_page')) {
                $table->unsignedSmallInteger('number_webpages_sub_type_landing_page')->default(0);
            }
        });
        if (Schema::hasTable('group_web_stats')) {
            Schema::table('group_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_web_stats', 'number_webpages_type_landing_page')) {
                    $table->unsignedSmallInteger('number_webpages_type_landing_page')->default(0);
                }
            });
            Schema::table('group_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_web_stats', 'number_webpages_sub_type_landing_page')) {
                    $table->unsignedSmallInteger('number_webpages_sub_type_landing_page')->default(0);
                }
            });
        }

        if (Schema::hasTable('organisation_web_stats')) {
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('organisation_web_stats', 'number_webpages_type_landing_page')) {
                    $table->unsignedSmallInteger('number_webpages_type_landing_page')->default(0);
                }
            });
            Schema::table('organisation_web_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('organisation_web_stats', 'number_webpages_sub_type_landing_page')) {
                    $table->unsignedSmallInteger('number_webpages_sub_type_landing_page')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (Schema::hasColumn('website_stats', 'number_webpages_type_landing_page')) {
                $table->dropColumn('number_webpages_type_landing_page');
            }
            if (Schema::hasColumn('website_stats', 'number_webpages_sub_type_landing_page')) {
                $table->dropColumn('number_webpages_sub_type_landing_page');
            }
        });

        Schema::table('group_web_stats', function (Blueprint $table) {
            if (Schema::hasColumn('group_web_stats', 'number_webpages_type_landing_page')) {
                $table->dropColumn('number_webpages_type_landing_page');
            }
            if (Schema::hasColumn('group_web_stats', 'number_webpages_sub_type_landing_page')) {
                $table->dropColumn('number_webpages_sub_type_landing_page');
            }
        });


        Schema::table('organisation_web_stats', function (Blueprint $table) {
            if (Schema::hasColumn('organisation_web_stats', 'number_webpages_type_landing_page')) {
                $table->dropColumn('number_webpages_type_landing_page');
            }
            if (Schema::hasColumn('organisation_web_stats', 'number_webpages_sub_type_landing_page')) {
                $table->dropColumn('number_webpages_sub_type_landing_page');
            }
        });
    }
};
