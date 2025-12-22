<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 04:45:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasProcurementStats
{
    public function procurementStatsFields(Blueprint $table): Blueprint
    {

        $table = $this->orgAgentStats($table);
        $table = $this->orgSuppliersStats($table);
        $table = $this->orgSupplierProductsStats($table);
        $table = $this->purchaseOrdersStats($table);
        $table = $this->stockDeliveriesStats($table);
        $table = $this->purchaseOrderTransactionsStats($table);

        return $this->stockDeliveryItemsStats($table);
    }

    public function orgAgentStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_agents')->default(0)->comment('Total number agens active+archived');
        $table->unsignedInteger('number_active_org_agents')->default(0)->comment('Active agents, status=true');
        $table->unsignedInteger('number_archived_org_agents')->default(0)->comment('Archived agents, status=false');

        return $table;
    }


    public function orgSuppliersStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_suppliers')->default(0)->comment('Active + Archived  suppliers');
        $table->unsignedInteger('number_active_org_suppliers')->default(0)->comment('Active suppliers, status=true');
        $table->unsignedInteger('number_archived_org_suppliers')->default(0)->comment('Archived suppliers status=false');

        if ($table->getTable() != 'agent_stats') {
            $table->unsignedInteger('number_independent_org_suppliers')->default(0)->comment('Active + Archived no agent suppliers');
            $table->unsignedInteger('number_active_independent_org_suppliers')->default(0)->comment('Active no agent suppliers, status=true');
            $table->unsignedInteger('number_archived_independent_org_suppliers')->default(0)->comment('Archived no agent suppliers status=false');

            $table->unsignedInteger('number_org_suppliers_in_agents')->default(0)->comment('Active + Archived suppliers');
            $table->unsignedInteger('number_active_org_suppliers_in_agents')->default(0)->comment('Active suppliers, status=true');
            $table->unsignedInteger('number_archived_org_suppliers_in_agents')->default(0)->comment('Archived suppliers status=false');
        }

        return $table;
    }


    public function orgSupplierProductsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_org_supplier_products')->default(0);
        $table->unsignedInteger('number_current_org_supplier_products')->default(0)->comment('status=true');
        $table->unsignedInteger('number_available_org_supplier_products')->default(0);
        $table->unsignedInteger('number_no_available_org_supplier_products')->default(0)->comment('only for state=active|discontinuing');

        foreach (OrgSupplierProductStateEnum::cases() as $productState) {
            $table->unsignedInteger('number_org_supplier_products_state_'.$productState->snake())->default(0);
        }

        return $table;
    }

    public function purchaseOrdersStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_purchase_orders')->default(0);
        $table->unsignedInteger('number_current_purchase_orders')->default(0)->comment('Number purchase orders (except: cancelled and not_received) ');
        $table->unsignedInteger('number_open_purchase_orders')->default(0)->comment('Number purchase orders (except: in_process,settled,cancelled,not_received)');


        foreach (PurchaseOrderStateEnum::cases() as $purchaseOrderState) {
            $table->unsignedInteger('number_purchase_orders_state_'.$purchaseOrderState->snake())->default(0);
        }


        foreach (PurchaseOrderDeliveryStateEnum::cases() as $purchaseOrderStatus) {
            $table->unsignedInteger('number_purchase_orders_delivery_state_'.$purchaseOrderStatus->snake())->default(0);
        }


        return $table;
    }

    public function purchaseOrderTransactionsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_purchase_order_transactions')->default(0);
        $table->unsignedInteger('number_current_purchase_order_transactions')->default(0)->comment('Number purchase order transactions (except: cancelled and not_received) ');
        $table->unsignedInteger('number_open_purchase_order_transactions')->default(0)->comment('Number purchase order transactions (except: in_process,settled,cancelled,not_received)');


        foreach (PurchaseOrderTransactionStateEnum::cases() as $case) {
            $table->unsignedInteger('number_purchase_order_transactions_state_'.$case->snake())->default(0);
        }


        foreach (PurchaseOrderTransactionDeliveryStateEnum::cases() as $purchaseOrderStatus) {
            $table->unsignedInteger('number_purchase_orders_transactions_delivery_state_'.$purchaseOrderStatus->snake())->default(0);
        }


        return $table;
    }


    public function stockDeliveriesStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_stock_deliveries')->default(0)->comment('Number supplier deliveries');
        $table->unsignedInteger('number_current_stock_deliveries')->default(0)->comment('Number supplier deliveries (except: cancelled and not_received) ');

        $table->unsignedInteger('number_is_costed_stock_deliveries')->default(0)->comment('is_costed=true');
        $table->unsignedInteger('number_is_not_costed_stock_deliveries')->default(0)->comment('is_costed=false');

        $table->unsignedInteger('number_is_costed_stock_deliveries_state_placed')->default(0)->comment('state=placed is_costed=true');
        $table->unsignedInteger('number_is_not_costed_stock_deliveries_state_placed')->default(0)->comment('state=placed  is_costed=true');



        foreach (StockDeliveryStateEnum::cases() as $case) {
            $table->unsignedInteger('number_stock_deliveries_state_'.$case->snake())->default(0);
        }


        return $table;
    }

    public function stockDeliveryItemsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_stock_delivery_items')->default(0)->comment('Number supplier deliveries');
        $table->unsignedInteger('number_stock_delivery_items_except_cancelled')->default(0)->comment('Number supplier deliveries');

        foreach (StockDeliveryItemStateEnum::cases() as $case) {
            $table->unsignedInteger('number_stock_delivery_items_state_'.$case->snake())->default(0);
        }


        return $table;
    }


}
