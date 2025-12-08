<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jul 2023 12:44:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasFulfilmentStats
{
    public function fulfilmentAssetsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_pallets')->default(0);
        $table->unsignedInteger('number_pallets_with_stored_items')->default(0);

        foreach (PalletTypeEnum::cases() as $type) {
            $table->unsignedInteger("number_pallets_type_{$type->snake()}")->default(0);
        }
        foreach (PalletStateEnum::cases() as $state) {
            $table->unsignedInteger("number_pallets_state_{$state->snake()}")->default(0);
            $table->unsignedInteger("number_pallets_with_stored_items_state_{$state->snake()}")->default(0);

        }
        foreach (PalletStatusEnum::cases() as $status) {
            $table->unsignedInteger("number_pallets_status_{$status->snake()}")->default(0);
            $table->unsignedInteger("number_pallets_with_stored_items_status_{$status->snake()}")->default(0);
        }

        return $this->storedItemsStatsFields($table);
    }

    public function storedItemsStatsFields(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_stored_items')->default(0);
        foreach (StoredItemStateEnum::cases() as $state) {
            if ($table->getTable() == 'pallets' and $state == StoredItemStateEnum::DISCONTINUED) {
                continue;
            }
            $table->unsignedInteger("number_stored_items_state_{$state->snake()}")->default(0);
        }

        return $table;
    }

    public function fulfilmentStats(Blueprint $table): Blueprint
    {

        $table = $this->fulfilmentAssetsStats($table);

        $table->unsignedInteger('number_pallet_deliveries')->default(0);

        foreach (PalletDeliveryStateEnum::cases() as $case) {
            $table->unsignedInteger("number_pallet_deliveries_state_{$case->snake()}")->default(0);
        }

        $table->unsignedInteger('number_pallet_returns')->default(0);

        foreach (PalletReturnStateEnum::cases() as $case) {
            $table->unsignedInteger("number_pallet_returns_state_{$case->snake()}")->default(0);
        }
        $table = $this->storedItemsAuditStats($table);

        return $this->recurringBillStats($table);
    }

    public function fulfilmentCustomersStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_customers_interest_pallets_storage')->default(0);
        $table->unsignedInteger('number_customers_interest_items_storage')->default(0);
        $table->unsignedInteger('number_customers_interest_dropshipping')->default(0);

        foreach (FulfilmentCustomerStatusEnum::cases() as $case) {
            $table->unsignedInteger("number_customers_status_{$case->snake()}")->default(0);
        }

        $table->unsignedInteger('number_customers_with_stored_items')->default(0);
        $table->unsignedInteger('number_customers_with_pallets')->default(0);

        foreach (StoredItemStateEnum::cases() as $state) {
            $table->unsignedInteger("number_customers_with_stored_items_state_{$state->snake()}")->default(0);
        }

        return $table;
    }

    public function recurringBillStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_recurring_bills')->default(0);
        foreach (RecurringBillStatusEnum::cases() as $case) {
            $table->unsignedInteger("number_recurring_bills_status_{$case->snake()}")->default(0);
        }

        return $table;
    }

    public function storedItemsAuditStats(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_stored_item_audits')->default(0);
        foreach (StoredItemAuditStateEnum::cases() as $case) {
            $table->unsignedInteger("number_stored_item_audits_state_{$case->snake()}")->default(0);
        }

        return $table;
    }
}
