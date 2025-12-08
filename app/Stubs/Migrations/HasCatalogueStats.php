<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jul 2023 12:33:47 Malaysia Time, plane Bali -> KL
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasCatalogueStats
{
    public function shopsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_shops')->default(0);
        $table->unsignedSmallInteger('number_current_shops')->default(0)->comment('state=open+closing_down');

        foreach (ShopStateEnum::cases() as $shopState) {
            $table->unsignedSmallInteger('number_shops_state_'.$shopState->snake())->default(0);
        }

        if ($table->getTable() != 'master_shop_stats') {
            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->unsignedSmallInteger('number_shops_type_'.$shopType->snake())->default(0);
            }
        }

        return $table;
    }

    public function productVariantFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_product_variants')->default(0);

        return $table;
    }

    public function catalogueStats(Blueprint $table): Blueprint
    {
        $table = $this->catalogueDepartmentStatsFields($table);

        $table->unsignedInteger('number_collection_categories')->default(0);
        $table->unsignedInteger('number_collections')->default(0);
        $table = $this->catalogueFamilyStats($table);
        $table = $this->assetStats($table);
        $table = $this->catalogueProductsStats($table);

        $table = $this->productVariantFields($table);

        return $this->topSellersStats($table);
    }

    public function catalogueDepartmentStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_departments')->default(0);
        $table->unsignedInteger('number_current_departments')->default(0);

        foreach (ProductCategoryStateEnum::cases() as $departmentState) {
            $table->unsignedInteger('number_departments_state_'.$departmentState->snake())->default(0);
        }

        return $table;
    }

    public function topSellersStats(Blueprint $table): Blueprint
    {
        $timesUpdate = ['1d', '1w', '1m', '1y', 'all'];
        foreach ($timesUpdate as $timeUpdate) {
            if ($table->getTable() != 'product_category_stats') {
                $table->unsignedInteger("top_{$timeUpdate}_department_id")->nullable();
                $table->foreign("top_{$timeUpdate}_department_id")->references('id')->on('product_categories');
            }

            $table->unsignedInteger("top_{$timeUpdate}_family_id")->nullable();
            $table->foreign("top_{$timeUpdate}_family_id")->references('id')->on('product_categories');

            $table->unsignedInteger("top_{$timeUpdate}_product_id")->nullable();
            $table->foreign("top_{$timeUpdate}_product_id")->references('id')->on('products');
        }

        return $table;
    }

    public function catalogueFamilyStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_sub_departments')->default(0);
        $table->unsignedSmallInteger('number_current_sub_departments')->default(0)->comment('state: active+discontinuing');
        foreach (ProductCategoryStateEnum::cases() as $familyState) {
            $table->unsignedInteger('number_sub_departments_state_'.$familyState->snake())->default(0);
        }

        $table->unsignedInteger('number_families')->default(0);
        $table->unsignedSmallInteger('number_current_families')->default(0)->comment('state: active+discontinuing');
        foreach (ProductCategoryStateEnum::cases() as $familyState) {
            $table->unsignedInteger('number_families_state_'.$familyState->snake())->default(0);
        }
        $table->unsignedInteger('number_orphan_families')->default(0);

        return $table;
    }

    public function assetStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_assets')->default(0);
        $table->unsignedInteger('number_current_assets')->default(0)->comment('state: active+discontinuing');
        $table->unsignedInteger('number_historic_assets')->default(0);

        return $this->assetStatsBis($table);
    }

    public function assetStatsBis(Blueprint $table): Blueprint
    {
        foreach (AssetStateEnum::cases() as $case) {
            $table->unsignedInteger('number_assets_state_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_assets_type_product')->default(0);
        $table->unsignedInteger('number_assets_type_service')->default(0);
        $table->unsignedInteger('number_assets_type_subscription')->default(0);
        $table->unsignedInteger('number_assets_type_rental')->default(0);
        $table->unsignedInteger('number_assets_type_charge')->default(0);
        $table->unsignedInteger('number_assets_type_shipping_zone')->default(0);

        return $table;
    }

    public function catalogueProductsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_products')->default(0);
        $table->unsignedInteger('number_current_products')->default(0)->comment('state: active+discontinuing');

        foreach (ProductStateEnum::cases() as $case) {
            $table->unsignedInteger('number_products_state_'.$case->snake())->default(0);
        }

        foreach (ProductStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_products_status_'.$case->snake())->default(0);
        }

        foreach (ProductTradeConfigEnum::cases() as $case) {
            $table->unsignedInteger('number_products_trade_config_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_rentals')->default(0);

        foreach (RentalStateEnum::cases() as $case) {
            $table->unsignedInteger('number_rentals_state_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_services')->default(0);

        foreach (ServiceStateEnum::cases() as $case) {
            $table->unsignedInteger('number_services_state_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_subscriptions')->default(0);

        foreach (ServiceStateEnum::cases() as $case) {
            $table->unsignedInteger('number_subscriptions_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function billableFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_charges')->default(0);
        foreach (ChargeStateEnum::cases() as $case) {
            $table->unsignedInteger('number_charges_state_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_shipping_zone_schemas')->default(0);
        foreach (ShippingZoneSchemaStateEnum::cases() as $case) {
            $table->unsignedInteger('number_shipping_zone_schemas_state_'.$case->snake())->default(0);
        }

        $table->unsignedInteger('number_shipping_zones')->default(0);

        $table->unsignedInteger('number_adjustments')->default(0);
        foreach (AdjustmentTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_adjustments_type_'.$case->snake())->default(0);
        }

        return $table;
    }
}
