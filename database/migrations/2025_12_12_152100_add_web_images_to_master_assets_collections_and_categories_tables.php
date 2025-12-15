<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Dec 2025 17:37:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('master_assets', 'web_images')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->jsonb('web_images')->nullable()->default(DB::raw("'{}'::jsonb"));
            });
        }

        if (!Schema::hasColumn('master_collections', 'web_images')) {
            Schema::table('master_collections', function (Blueprint $table) {
                $table->jsonb('web_images')->nullable()->default(DB::raw("'{}'::jsonb"));
            });
        }

        if (!Schema::hasColumn('master_product_categories', 'web_images')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->jsonb('web_images')->nullable()->default(DB::raw("'{}'::jsonb"));
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasColumn('master_assets', 'web_images')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->dropColumn('web_images');
            });
        }

        if (Schema::hasColumn('master_collections', 'web_images')) {
            Schema::table('master_collections', function (Blueprint $table) {
                $table->dropColumn('web_images');
            });
        }

        if (Schema::hasColumn('master_product_categories', 'web_images')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->dropColumn('web_images');
            });
        }

    }
};
