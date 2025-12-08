<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Jul 2024 18:21:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasOrderAmountTotals
{
    public function orderTotalAmounts(Blueprint $table): Blueprint
    {

        $table->decimal('gross_amount', 16)->default(0)
            ->comment('Total asserts amount (excluding charges and shipping) before discounts');

        $table->decimal('goods_amount', 16)->default(0);
        $table->decimal('services_amount', 16)->default(0);

        if ($table->getTable() == 'recurring_bills' or $table->getTable() == 'invoices') {
            $table->decimal('rental_amount', 16)->default(0);
        }

        if ($table->getTable() == 'orders' or $table->getTable() == 'invoices') {
            $table->decimal('charges_amount', 16)->default(0);
            $table->decimal('shipping_amount', 16)->default(0)->nullable();
            $table->decimal('insurance_amount', 16)->default(0)->nullable();

        }

        $table->decimal('net_amount', 16)->default(0);
        $table->decimal('grp_net_amount', 16)->nullable();
        $table->decimal('org_net_amount', 16)->nullable();
        $table->unsignedSmallInteger('tax_category_id')->index();
        $table->foreign('tax_category_id')->references('id')->on('tax_categories');
        $table->decimal('tax_amount', 16)->default(0);
        $table->decimal('total_amount', 16)->default(0);

        if ($table->getTable() != 'recurring_bills') {
            $table->decimal('payment_amount', 16)->default(0);
        }

        return $table;
    }

    public function currencyFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('currency_id');
        $table->foreign('currency_id')->references('id')->on('currencies');
        $table->decimal('grp_exchange', 16, 4)->nullable();
        $table->decimal('org_exchange', 16, 4)->nullable();

        return $table;
    }
}
