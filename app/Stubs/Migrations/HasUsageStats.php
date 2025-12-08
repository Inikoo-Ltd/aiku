<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 17:38:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasUsageStats
{
    use HasAmounts;

    public function usageStats(Blueprint $table): Blueprint
    {
        $table->timestampTz('first_used_at')->nullable();
        $table->timestampTz('last_used_at')->nullable();

        return $this->usageBaseStats($table);
    }

    public function usageBaseStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_customers')->default(0);
        $table->unsignedInteger('number_orders')->default(0);
        $table->unsignedInteger('number_invoices')->default(0);
        $table->unsignedInteger('number_delivery_notes')->default(0);

        $allowedCurrencies = $this->allowedCurrencies($table);

        if ($allowedCurrencies['shop']) {
            $table->decimal('amount')->default(0);
        }

        if ($allowedCurrencies['org']) {
            $table->decimal('org_amount')->default(0);
        }

        if ($allowedCurrencies['grp']) {
            $table->decimal('grp_amount')->default(0);
        }

        return $table;
    }
}
