<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 23:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Helpers\TaxNumber\Concerns\HasTaxNumberType;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxNumber;
use Arr;

class UpdateTaxNumber
{
    use WithActionUpdate;
    use HasTaxNumberType;

    public function handle(TaxNumber $taxNumber, array $modelData, bool $strict = true): TaxNumber
    {

        $oldChecksumData = array($taxNumber->number,$taxNumber->country_id);
        if (Arr::has($modelData, 'number')) {
            $oldChecksumData[0] = $modelData['number'];
        }
        if (Arr::has($modelData, 'country_id')) {
            $oldChecksumData[1] = $modelData['country_id'];
            $country = Country::find($modelData['country_id']);
            if ($country) {
                data_set($modelData, 'country_code', $country->code);
                data_set($modelData, 'type', $this->getTaxNumberType($country));
            }
        }
        data_set($modelData, 'checksum', hash('sha512', implode('', $oldChecksumData)));


        $taxNumber = $this->update($taxNumber, $modelData, ['data']);
        $changes = Arr::except($taxNumber->getChanges(), ['updated_at', 'last_fetched_at']);


        if (Arr::hasAny($changes, ['number', 'country_id',])) {

            if ($strict) {
                $taxNumber = $this->update($taxNumber, [
                    'status' => TaxNumberStatusEnum::UNKNOWN,
                    'valid' => false,
                ]);
                $taxNumber->refresh();
            }

            if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
                $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
            } elseif ($taxNumber->type == TaxNumberTypeEnum::GB_VAT) {
                $taxNumber = ValidateGBTaxNumber::run($taxNumber);
            }
        }

        return $taxNumber;
    }

}
