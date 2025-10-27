<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 17:48:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxNumber;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTaxNumber
{
    use AsAction;

    public function handle(Shop|Customer $owner, array $modelData = [], bool $strict = true): TaxNumber
    {
        if ($strict) {
            $type = TaxNumberTypeEnum::UNKNOWN;
            if ($countryID = Arr::get($modelData, 'country_id')) {
                $country = Country::find($countryID);
                if ($country) {
                    if ($country->code == 'GB') {
                        $type = TaxNumberTypeEnum::GB_VAT;
                    } elseif (Country::isInEU($country->code)) {
                        $type = TaxNumberTypeEnum::EU_VAT;
                    } else {
                        $type = TaxNumberTypeEnum::OTHER;
                    }

                    data_set($modelData, 'country_code', $country->code, false);
                }
            }
            data_set($modelData, 'type', $type, false);
        }

        /** @var TaxNumber $taxNumber */
        $taxNumber = $owner->taxNumber()->create($modelData);

        if ($strict) {
            if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
                $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
            } elseif ($taxNumber->type == TaxNumberTypeEnum::GB_VAT) {
                $taxNumber = ValidateGBTaxNumber::run($taxNumber);
            }
        }

        return $taxNumber;
    }
}
