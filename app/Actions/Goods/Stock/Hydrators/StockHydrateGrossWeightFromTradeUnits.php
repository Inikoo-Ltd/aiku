<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 14:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Models\Goods\Stock;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class StockHydrateGrossWeightFromTradeUnits
{
    use AsAction;
    use WithWeightFromTradeUnits;

    private Stock $stock;

    public function __construct(Stock $stock)
    {
        $this->stock = $stock;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->stock->id))->dontRelease()];
    }

    public function handle(Stock $stock): void
    {

        $stock->updateQuietly(
            [
                'gross_weight' => $this->getWeightFromTradeUnits($stock),
            ]
        );

    }


}
