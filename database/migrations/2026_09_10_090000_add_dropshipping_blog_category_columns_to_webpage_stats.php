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
     * WebpageSubTypeEnum case, so the blog categories of a dropshipping shop need theirs.
     */
    private const COLUMNS = [
        'website_stats' => [
            'number_webpages_sub_type_integrations_guides',
            'number_webpages_sub_type_dropshipping_guides',
        ],
        'webpage_stats' => [
            'number_child_webpages_sub_type_integrations_guides',
            'number_child_webpages_sub_type_dropshipping_guides',
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
