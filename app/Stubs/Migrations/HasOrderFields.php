<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 18:39:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasOrderFields
{
    public function orderMoneyFields(Blueprint $table): Blueprint
    {
        $table->decimal('gross_amount', 16)->default(0)->comment('net amount before discounts');
        $table->decimal('net_amount', 16)->default(0);
        $table->decimal('grp_net_amount', 16)->nullable();
        $table->decimal('org_net_amount', 16)->nullable();
        $table->unsignedSmallInteger('tax_category_id')->index();
        $table->foreign('tax_category_id')->references('id')->on('tax_categories');
        $table->decimal('grp_exchange', 22, 10)->nullable();
        $table->decimal('org_exchange', 22, 10)->nullable();
        return $table;
    }
}
