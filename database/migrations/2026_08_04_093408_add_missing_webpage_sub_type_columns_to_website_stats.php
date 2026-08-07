<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * WebsiteHydrateWebpages and WebpageHydrateChildWebpages write one column per
     * WebpageSubTypeEnum case, so a case without its column makes the hydrator fail on
     * every webpage change. Several cases were added to the enum without one.
     */
    private const COLUMNS = [
        'website_stats' => [
            'number_webpages_sub_type_davids_travel_blog',
            'number_webpages_sub_type_tips',
        ],
        'webpage_stats' => [
            'number_child_webpages_sub_type_landing_page',
            'number_child_webpages_sub_type_mailshot',
            'number_child_webpages_sub_type_davids_travel_blog',
            'number_child_webpages_sub_type_tips',
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }
            });
        }
    }


    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
