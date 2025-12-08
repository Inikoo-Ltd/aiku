<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 22:58:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasAmounts
{
    public function allowedCurrencies(Blueprint $table): array
    {
        $tableName = $table->getTable();
        $allowedCurrencies = [
            'grp' => true,
            'org' => true,
            'shop' => true,
        ];
        if (preg_match('/group|master/', $tableName) || $tableName == 'payment_service_provider_stats') {
            $allowedCurrencies['org'] = false;
            $allowedCurrencies['shop'] = false;
        }

        if (str_contains($tableName, 'organisation') || in_array($tableName, ['org_payment_service_provider_stats', 'payment_account_stats'])) {
            $allowedCurrencies['shop'] = false;
        }

        return $allowedCurrencies;
    }
}
