<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Oct 2024 16:04:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait IsProcurementOrder
{
    use HasGroupOrganisationRelationship;

    protected function headProcurementOrder($table): Blueprint
    {
        $table->increments('id');
        $table = $this->groupOrgRelationship($table);
        $table->string('slug')->unique()->collation('und_ns');
        $table->string('parent_type')->comment('OrgAgent|OrgSupplier|Partner(intra-group sales)')->index();
        $table->unsignedInteger('parent_id')->index();
        $table->string('parent_code')->index()->collation('und_ns')->comment('Parent code on the time of consolidation');
        $table->string('parent_name')->index()->comment('Parent name on the time of consolidation');
        $table->string('reference')->index();

        return $table;
    }

    protected function bodyProcurementOrder($table)
    {

        $table->unsignedSmallInteger('agent_id')->nullable();
        $table->foreign('agent_id')->references('id')->on('agents');
        $table->unsignedInteger('supplier_id')->nullable();
        $table->foreign('supplier_id')->references('id')->on('suppliers');
        $table->unsignedSmallInteger('partner_id')->nullable();
        $table->foreign('partner_id')->references('id')->on('organisations');

        return $table;
    }

    protected function footerProcurementOrder($table)
    {
        $table->jsonb('data');

        $table->timestampsTz();
        $table->datetimeTz('fetched_at')->nullable();
        $table->datetimeTz('last_fetched_at')->nullable();
        $table->softDeletesTz();
        $table->string('source_id')->nullable()->unique();
        $table->index(['parent_id', 'parent_type']);

        return $table;
    }

    protected function statsProcurementOrder($table)
    {
        $table->float('gross_weight', 16)->default(null)->nullable();
        $table->float('net_weight', 16)->default(null)->nullable();

        return $table;
    }

    protected function costingProcurementOrder($table)
    {
        $table->unsignedSmallInteger('currency_id');
        $table->foreign('currency_id')->references('id')->on('currencies');
        $table->decimal('grp_exchange', 16, 4)->nullable();
        $table->decimal('org_exchange', 16, 4)->nullable();

        $table->boolean('is_costed')->default(false)->index();
        $table->jsonb('cost_data');

        $table->decimal('cost_items', 16)->default(null)->nullable();
        $table->decimal('cost_extra', 16)->default(null)->nullable();
        $table->decimal('cost_shipping', 16)->default(null)->nullable();
        $table->decimal('cost_duties', 16)->default(null)->nullable();
        $table->decimal('cost_tax', 16)->default(0);
        $table->decimal('cost_total', 16)->default(0);

        return $table;
    }
}
