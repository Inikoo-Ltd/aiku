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
     * WebpageTypeEnum and WebpageSubTypeEnum case, so the new system page cases need theirs.
     */
    private const COLUMNS = [
        'website_stats' => [
            'number_webpages_type_system_page',
            'number_webpages_sub_type_login_page',
            'number_webpages_sub_type_register_page',
            'number_webpages_sub_type_forgot_password_page',
            'number_webpages_sub_type_blog_dashboard_page',
        ],
        'webpage_stats' => [
            'number_child_webpages_type_system_page',
            'number_child_webpages_sub_type_login_page',
            'number_child_webpages_sub_type_register_page',
            'number_child_webpages_sub_type_forgot_password_page',
            'number_child_webpages_sub_type_blog_dashboard_page',
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
