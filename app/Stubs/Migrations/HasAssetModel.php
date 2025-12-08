<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 23:29:40 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Billables\Shipping\ShippingStateEnum;
use App\Enums\Billables\Subscription\SubscriptionStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasAssetModel
{
    use HasGroupOrganisationRelationship;

    public function assetModelFields(Blueprint $table): Blueprint
    {
        $table->string('slug')->unique()->collation('und_ns');
        $table->string('code')->index()->collation('und_ns');
        $table->string('name', 255)->nullable();
        $table->text('description')->nullable()->fulltext();

        if ($table->getTable() != 'charges') {
            $table->decimal('price', 18)->nullable();
            $table->decimal('units', 9, 3)->default(1);
            $table->string('unit');
        }

        $table->jsonb('data');
        $table->jsonb('settings');

        $table->unsignedSmallInteger('currency_id');
        $table->foreign('currency_id')->references('id')->on('currencies');
        $table->unsignedInteger('current_historic_asset_id')->index()->nullable();
        $table->foreign('current_historic_asset_id')->references('id')->on('historic_assets');

        return $table;
    }

    public function productFields(Blueprint $table): Blueprint
    {
        $table->increments('id');
        $table = $this->groupOrgRelationship($table);
        $table->unsignedSmallInteger('shop_id')->nullable();
        $table->foreign('shop_id')->references('id')->on('shops');
        $table->unsignedInteger('asset_id')->nullable();
        $table->foreign('asset_id')->references('id')->on('assets');
        $table->unsignedInteger('family_id')->nullable();
        $table->unsignedInteger('sub_department_id')->nullable();
        $table->unsignedInteger('department_id')->nullable();

        return $table;
    }

    public function billableFields(Blueprint $table): Blueprint
    {
        $table->smallIncrements('id');
        $table = $this->groupOrgRelationship($table);
        $table->unsignedSmallInteger('shop_id')->nullable();
        $table->foreign('shop_id')->references('id')->on('shops');
        $table->unsignedInteger('asset_id')->nullable();
        $table->foreign('asset_id')->references('id')->on('assets');

        if ($table->getTable() == 'shippings') {
            $table->boolean('structural')->default(false);
        }

        $table->boolean('status')->default(false)->index();

        if ($table->getTable() == 'charges') {
            $table->string('state')->default(ChargeStateEnum::IN_PROCESS)->index();
            $table->string('type')->index();
            $table->string('trigger')->index();
        } elseif ($table->getTable() == 'shippings') {
            $table->string('state')->default(ShippingStateEnum::IN_PROCESS)->index();
        } elseif ($table->getTable() == 'subscriptions') {
            $table->string('state')->default(SubscriptionStateEnum::IN_PROCESS)->index();
        }

        $table = $this->assetModelFields($table);
        $table->timestampsTz();
        $table->datetimeTz('fetched_at')->nullable();
        $table->datetimeTz('last_fetched_at')->nullable();

        if ($table->getTable() != 'adjustments') {
            $table->softDeletes();
        }
        if (! in_array($table->getTable(), ['subscriptions', 'charges'])) {
            $table->string('historic_source_id')->nullable()->unique();
        }
        if ($table->getTable() != 'subscriptions') {
            $table->string('source_id')->nullable()->unique();
        }

        return $table;
    }
}
