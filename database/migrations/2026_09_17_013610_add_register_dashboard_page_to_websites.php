<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * WebsiteHydrateWebpages and WebpageHydrateChildWebpages write one column per
     * WebpageSubTypeEnum case, so the register dashboard system page needs its stats columns
     * alongside the website column wiring it to the website.
     */
    private const STATS_COLUMNS = [
        'website_stats' => ['number_webpages_sub_type_register_dashboard_page'],
        'webpage_stats' => ['number_child_webpages_sub_type_register_dashboard_page'],
    ];

    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedBigInteger('register_dashboard_page_id')->nullable();
            $table->foreign('register_dashboard_page_id')->references('id')->on('webpages');
        });

        foreach (self::STATS_COLUMNS as $tableName => $columns) {
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
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['register_dashboard_page_id']);
        });

        foreach (self::STATS_COLUMNS as $tableName => $columns) {
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
