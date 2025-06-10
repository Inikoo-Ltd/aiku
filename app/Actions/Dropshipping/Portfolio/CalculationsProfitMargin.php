<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\OrgAction;

class CalculationsProfitMargin extends OrgAction
{
    public function handle(float $retailPrice, float $costPriceExVat, $vatRate = 0.20): float
    {
        return ((($retailPrice / $vatRate) - $costPriceExVat) / ($retailPrice / $vatRate)) * 100;
    }
}
