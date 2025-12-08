<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Nov 2023 21:56:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockAuditDelta\OrgStockAuditDeltaTypeEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasInventoryStats
{
    public function inventoryStatsFields(Blueprint $table): Blueprint
    {
        $table = $this->warehousesStats($table);
        $table = $this->warehousesAreasStats($table);
        $table = $this->locationsStats($table);
        $table = $this->orgStockFamiliesStats($table);
        $table = $this->orgStockStats($table);
        $table = $this->orgStocksMovementsStats($table);

        return $this->orgStocksAuditStats($table);
    }

    public function warehousesStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_warehouses')->default(0);
        foreach (WarehouseStateEnum::cases() as $case) {
            $table->unsignedInteger('number_warehouses_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function warehousesAreasStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_warehouse_areas')->default(0);

        return $table;
    }

    public function locationsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_locations')->default(0);
        $table->unsignedSmallInteger('number_locations_status_operational')->default(0);
        $table->unsignedSmallInteger('number_locations_status_broken')->default(0);
        $table->unsignedSmallInteger('number_empty_locations')->default(0);
        $table->unsignedSmallInteger('number_locations_no_stock_slots')->default(0);

        $table->unsignedSmallInteger('number_locations_allow_stocks')->default(0);
        $table->unsignedSmallInteger('number_locations_allow_fulfilment')->default(0);
        $table->unsignedSmallInteger('number_locations_allow_dropshipping')->default(0);

        $table->decimal('stock_value', 14)->default(0);
        $table->decimal('stock_commercial_value', 14)->default(0);

        return $table;
    }

    public function orgStockFamiliesStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_stock_families')->default(0);
        $table->unsignedInteger('number_current_org_stock_families')->default(0)->comment('active + discontinuing');

        foreach (OrgStockFamilyStateEnum::cases() as $stockFamilyState) {
            $table->unsignedInteger('number_org_stock_families_state_'.$stockFamilyState->snake())->default(0);
        }

        return $table;
    }

    public function orgStockStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_stocks')->default(0);
        $table->unsignedInteger('number_current_org_stocks')->default(0)->comment('active + discontinuing');
        $table->unsignedInteger('number_dropped_org_stocks')->default(0)->comment('discontinued + abnormality');

        foreach (OrgStockStateEnum::cases() as $stockState) {
            $table->unsignedInteger('number_org_stocks_state_'.$stockState->snake())->default(0);
        }
        foreach (OrgStockQuantityStatusEnum::cases() as $stockQuantityStatus) {
            $table->unsignedInteger('number_org_stocks_quantity_status_'.$stockQuantityStatus->snake())->default(0);
        }

        return $table;
    }

    public function orgStocksMovementsStats(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_org_stock_movements')->default(0);
        foreach (OrgStockMovementTypeEnum::cases() as $case) {
            $table->unsignedBigInteger("number_org_stock_movements_type_{$case->snake()}")->default(0);
        }
        foreach (OrgStockMovementFlowEnum::cases() as $case) {
            $table->unsignedBigInteger("number_org_stock_movements_flow_{$case->snake()}")->default(0);
        }

        return $table;
    }

    public function orgStocksAuditStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_stock_audits')->default(0);
        foreach (StoredItemAuditStateEnum::cases() as $case) {
            $table->unsignedInteger("number_org_stock_audits_state_{$case->snake()}")->default(0);
        }

        $table->unsignedInteger('number_org_stock_audit_deltas')->default(0);
        foreach (OrgStockAuditDeltaTypeEnum::cases() as $case) {
            $table->unsignedInteger("number_org_stock_audit_delta_type_{$case->snake()}")->default(0);
        }

        return $table;
    }
}
