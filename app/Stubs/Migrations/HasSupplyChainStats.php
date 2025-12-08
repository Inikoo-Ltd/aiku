<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Dec 2024 01:51:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasSupplyChainStats
{
    public function agentStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_agents')->default(0)->comment('Total number agens active+archived');
        $table->unsignedInteger('number_active_agents')->default(0)->comment('Active agents, status=true');
        $table->unsignedInteger('number_archived_agents')->default(0)->comment('Archived agents, status=false');

        return $table;
    }

    public function suppliersStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_suppliers')->default(0)->comment('Active + Archived  suppliers');
        $table->unsignedInteger('number_active_suppliers')->default(0)->comment('Active suppliers, status=true');
        $table->unsignedInteger('number_archived_suppliers')->default(0)->comment('Archived suppliers status=false');

        if ($table->getTable() != 'agent_stats') {
            $table->unsignedInteger('number_independent_suppliers')->default(0)->comment('Active + Archived no agent suppliers');
            $table->unsignedInteger('number_active_independent_suppliers')->default(0)->comment('Active no agent suppliers, status=true');
            $table->unsignedInteger('number_archived_independent_suppliers')->default(0)->comment('Archived no agent suppliers status=false');

            $table->unsignedInteger('number_suppliers_in_agents')->default(0)->comment('Active + Archived suppliers');
            $table->unsignedInteger('number_active_suppliers_in_agents')->default(0)->comment('Active suppliers, status=true');
            $table->unsignedInteger('number_archived_suppliers_in_agents')->default(0)->comment('Archived suppliers status=false');
        }

        return $table;
    }

    public function supplierProductsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_supplier_products')->default(0);
        $table->unsignedInteger('number_current_supplier_products')->default(0)->comment('state=active|discontinuing');
        $table->unsignedInteger('number_available_supplier_products')->default(0);
        $table->unsignedInteger('number_no_available_supplier_products')->default(0)->comment('only for state=active|discontinuing');

        foreach (SupplierProductStateEnum::cases() as $productState) {
            $table->unsignedInteger('number_supplier_products_state_'.$productState->snake())->default(0);
        }

        return $table;
    }
}
