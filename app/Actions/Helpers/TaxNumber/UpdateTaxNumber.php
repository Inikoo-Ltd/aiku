<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 23:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\TaxNumber;

class UpdateTaxNumber
{
    use WithActionUpdate;

    public function handle(TaxNumber $taxNumber, array $modelData): TaxNumber
    {
        $taxNumber = $this->update($taxNumber, $modelData, ['data']);

        if ($taxNumber->wasChanged(['type', 'number', 'country_code']) && $taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
            $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
        }

        return $taxNumber;
    }
}
