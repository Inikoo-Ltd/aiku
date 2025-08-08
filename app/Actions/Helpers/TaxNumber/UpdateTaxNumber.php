<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 23:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\TaxNumber;

class UpdateTaxNumber
{
    use WithActionUpdate;

    public function handle(TaxNumber $taxNumber, array $modelData): TaxNumber
    {
        $taxNumber = ValidateTaxNumber::run($taxNumber);
        return $this->update($taxNumber, $modelData, ['data']);
    }
}
