<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Oct 2025 11:35:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Refactor by Junie (JetBrains AI)
 * Created: Tue, 28 Oct 2025
 */

namespace App\Actions\Helpers\TaxNumber\Concerns;

use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\Country;

trait HasTaxNumberType
{
    public function getTaxNumberType(?Country $country): TaxNumberTypeEnum
    {
        $type = TaxNumberTypeEnum::OTHER;
        if (!$country) {
            return $type;
        }
        if (Country::isInEU($country->code)) {
            return TaxNumberTypeEnum::EU_VAT;
        }
        if ($country->code === 'GB') {
            return TaxNumberTypeEnum::GB_VAT;
        }

        return $type;
    }
}
