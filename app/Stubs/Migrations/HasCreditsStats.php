<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Aug 2024 12:00:35 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasCreditsStats
{
    public function getCreditTransactionsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_credit_transactions')->default(0);

        if ($table->getTable() == 'customer_stats') {
            return $table;
        }

        $table->unsignedInteger('number_customers_with_balances')->default(0);
        $table->unsignedInteger('number_customers_with_positive_balances')->default(0);
        $table->unsignedInteger('number_customers_with_negative_balances')->default(0);

        if ($table->getTable() == 'shop_stats') {
            $table->decimal('customer_balances_amount', 16)->default(0);
            $table->decimal('customer_positive_balances_amount', 16)->default(0);
            $table->decimal('customer_negative_balances_amount', 16)->default(0);

            $table = $this->getOrganisationBalanceStats($table);
            $table = $this->getGroupBalanceStats($table);
        } elseif ($table->getTable() == 'organisation_accounting_stats') {
            $table = $this->getOrganisationBalanceStats($table);
            $table = $this->getGroupBalanceStats($table);
        } elseif ($table->getTable() == 'group_accounting_stats') {
            $table = $this->getGroupBalanceStats($table);
        }

        return $table;
    }

    public function getOrganisationBalanceStats(Blueprint $table): Blueprint
    {
        $table->decimal('customer_balances_org_amount', 16)->default(0);
        $table->decimal('customer_positive_balances_org_amount', 16)->default(0);
        $table->decimal('customer_negative_balances_org_amount', 16)->default(0);

        return $table;
    }

    public function getGroupBalanceStats(Blueprint $table): Blueprint
    {
        $table->decimal('customer_balances_grp_amount', 16)->default(0);
        $table->decimal('customer_positive_balances_grp_amount', 16)->default(0);
        $table->decimal('customer_negative_balances_grp_amount', 16)->default(0);

        return $table;
    }

    public function getTopUpsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_top_ups')->default(0);
        foreach (TopUpStatusEnum::cases() as $state) {
            $table->unsignedInteger("number_top_ups_status_{$state->snake()}")->default(0);
        }

        return $table;
    }
}
