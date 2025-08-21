<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Currency;

use App\Models\Helpers\Currency;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class SetCurrencyHistoricFields
{
    use AsAction;

    public function handle(Currency $currency, ?Carbon $date): void
    {
        $toUpdate =
            [
                'store_historic_data' => true
            ];

        if ($date && !($currency->historic_data_since && $currency->historic_data_since < $date)) {
            $toUpdate['historic_data_since'] = $date->toDate();

        }

        $currency->update($toUpdate);
    }
}
