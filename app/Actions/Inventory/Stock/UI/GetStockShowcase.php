<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Stock\UI;

use App\Models\Inventory\Stock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockShowcase
{
    use AsObject;

    public function handle(Stock $stock): array
    {
        return [
            []
        ];
    }
}
