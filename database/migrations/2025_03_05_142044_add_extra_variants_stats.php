<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 22:21:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $this->variantFields($table);
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $this->variantFields($table);
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $this->variantFields($table);
        });
    }

    public function down(): void
    {
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $this->rollBackVariantFields($table);
        });

        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $this->rollBackVariantFields($table);
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $this->rollBackVariantFields($table);
        });
    }

    public function variantFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_current_product_variants')->default(0)->comment('state: active+discontinuing');

        foreach (ProductStateEnum::cases() as $case) {
            $table->unsignedInteger('number_product_variants_state_'.$case->snake())->default(0);
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_product_variants_status_'.$case->snake())->default(0);
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $table->unsignedInteger('number_product_variants_trade_config_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_products_with_variants')->default(0);
        $table->unsignedInteger('number_current_products_with_variants')->default(0)->comment('state: active+discontinuing');

        foreach (ProductStateEnum::cases() as $case) {
            $table->unsignedInteger('number_products_with_variants_state_'.$case->snake())->default(0);
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_products_with_variants_status_'.$case->snake())->default(0);
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $table->unsignedInteger('number_products_with_variants_trade_config_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function rollBackVariantFields(Blueprint $table): Blueprint
    {
        $table->dropColumn('number_current_product_variants');
        foreach (ProductStateEnum::cases() as $case) {
            $table->dropColumn('number_product_variants_state_'.$case->snake());
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $table->dropColumn('number_product_variants_status_'.$case->snake());
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $table->dropColumn('number_product_variants_trade_config_'.$case->snake());
        }

        $table->dropColumn('number_products_with_variants');
        $table->dropColumn('number_current_products_with_variants');

        foreach (ProductStateEnum::cases() as $case) {
            $table->dropColumn('number_products_with_variants_state_'.$case->snake());
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $table->dropColumn('number_products_with_variants_status_'.$case->snake());
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $table->dropColumn('number_products_with_variants_trade_config_'.$case->snake());
        }

        return $table;
    }
};
