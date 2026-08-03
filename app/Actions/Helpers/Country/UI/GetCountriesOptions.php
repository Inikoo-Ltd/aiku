<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Country\UI;

use App\Models\Helpers\Country;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCountriesOptions
{
    use AsObject;

    public function handle(bool $ignoreStatus = false, $ignoreShowInAddress = false): array
    {
        $selectOptions = [];
        /** @var Country $country */
        foreach (
            Country::when(!$ignoreStatus, fn ($q) => $q->where('status', true))
                ->when(!$ignoreShowInAddress, fn ($q) => $q->where('show_in_address', true))
                ->get() as $country
        ) {
            $selectOptions[$country->id] =
                [
                    'label' => $country->name.' ('.$country->code.')',
                    'code'  => $country->code,
                    'id'    => $country->id
                ];
        }

        return $selectOptions;
    }
}
