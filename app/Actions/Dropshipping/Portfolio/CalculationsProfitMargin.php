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
    public function handle(float $revenue, float $cost): float
    {

        if ($revenue == 0) {
            return 1;
        }

        return ($revenue - $cost) / $revenue;
    }
}
