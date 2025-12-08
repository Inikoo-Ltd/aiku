<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 06:57:34 CST, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasFulfilmentDelivery
{
    use HasGroupOrganisationRelationship;

    public function getPalletIOFields(Blueprint $table): Blueprint
    {

        $table = $this->groupOrgRelationship($table);
        $table->string('slug')->unique()->collation('und_ns');
        $table->ulid()->unique()->index();
        $table->unsignedInteger('fulfilment_customer_id');
        $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
        $table->unsignedSmallInteger('fulfilment_id');
        $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
        $table->unsignedSmallInteger('warehouse_id')->nullable();
        $table->foreign('warehouse_id')->references('id')->on('warehouses');
        $table->string('customer_reference')->nullable()->index();
        $table->string('reference')->unique()->index();

        return $table;
    }
}
