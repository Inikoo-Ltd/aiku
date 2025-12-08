<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 May 2023 15:48:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasSalesTransactionParents
{
    public function salesTransactionParents(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('shop_id')->index();
        $table->foreign('shop_id')->references('id')->on('shops');
        $table->unsignedInteger('customer_id')->index();
        $table->foreign('customer_id')->references('id')->on('customers');
        $table->unsignedInteger('customer_client_id')->nullable()->index();
        $table->foreign('customer_client_id')->references('id')->on('customer_clients');

        if ($table->getTable() === 'invoices' or $table->getTable() === 'transactions') {
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        }
        if ($table->getTable() === 'invoices') {
            $table->unsignedInteger('recurring_bill_id')->nullable()->index();
        }

        return $table;
    }
}
