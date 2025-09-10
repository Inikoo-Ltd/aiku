<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 09:12:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('master_assets', 'bucket_images')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->boolean('bucket_images')->nullable()->comment('images following the buckets');
            });
        }

        if (!Schema::hasColumn('products', 'bucket_images')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('bucket_images')->nullable()->comment('images following the buckets');
            });
        }

        if (!Schema::hasColumn('master_product_categories', 'bucket_images')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->boolean('bucket_images')->nullable()->comment('images following the buckets');
            });
        }

        if (!Schema::hasColumn('product_categories', 'bucket_images')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->boolean('bucket_images')->nullable()->comment('images following the buckets');
            });
        }

        if (!Schema::hasColumn('trade_units', 'bucket_images')) {
            Schema::table('trade_units', function (Blueprint $table) {
                $table->boolean('bucket_images')->nullable()->comment('images following the buckets');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_assets', 'bucket_images')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->dropColumn('bucket_images');
            });
        }

        if (Schema::hasColumn('products', 'bucket_images')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('bucket_images');
            });
        }

        if (Schema::hasColumn('master_product_categories', 'bucket_images')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->dropColumn('bucket_images');
            });
        }

        if (Schema::hasColumn('product_categories', 'bucket_images')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('bucket_images');
            });
        }

        if (Schema::hasColumn('trade_units', 'bucket_images')) {
            Schema::table('trade_units', function (Blueprint $table) {
                $table->dropColumn('bucket_images');
            });
        }
    }
};
