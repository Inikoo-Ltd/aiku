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
        if (!Schema::hasColumn('master_assets', 'lifestyle_image_id')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->unsignedInteger('lifestyle_image_id')->nullable();
                $table->foreign('lifestyle_image_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('products', 'lifestyle_image_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('lifestyle_image_id')->nullable();
                $table->foreign('lifestyle_image_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('master_product_categories', 'lifestyle_image_id')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->unsignedInteger('lifestyle_image_id')->nullable();
                $table->foreign('lifestyle_image_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('product_categories', 'lifestyle_image_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->unsignedInteger('lifestyle_image_id')->nullable();
                $table->foreign('lifestyle_image_id')->references('id')->on('media')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('trade_units', 'lifestyle_image_id')) {
            Schema::table('trade_units', function (Blueprint $table) {
                $table->unsignedInteger('lifestyle_image_id')->nullable();
                $table->foreign('lifestyle_image_id')->references('id')->on('media')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('master_assets', 'lifestyle_image_id')) {
            Schema::table('master_assets', function (Blueprint $table) {
                $table->dropColumn('lifestyle_image_id');
            });
        }

        if (Schema::hasColumn('products', 'lifestyle_image_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('lifestyle_image_id');
            });
        }

        if (Schema::hasColumn('master_product_categories', 'lifestyle_image_id')) {
            Schema::table('master_product_categories', function (Blueprint $table) {
                $table->dropColumn('lifestyle_image_id');
            });
        }

        if (Schema::hasColumn('product_categories', 'lifestyle_image_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('lifestyle_image_id');
            });
        }

        if (Schema::hasColumn('trade_units', 'lifestyle_image_id')) {
            Schema::table('trade_units', function (Blueprint $table) {
                $table->dropColumn('lifestyle_image_id');
            });
        }
    }
};
