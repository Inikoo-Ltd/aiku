<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 17:48:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TaxNumber;

use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\TaxNumber;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTaxNumber
{
    use AsAction;

    public function handle(Shop|Customer $owner, array $modelData = []): TaxNumber
    {

        /** @var TaxNumber $taxNumber */
        $taxNumber = $owner->taxNumber()->create($modelData);

        if ($taxNumber->type == TaxNumberTypeEnum::EU_VAT) {
            $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
        }

        return $taxNumber;
    }
}
