<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Hydrators;

use App\Models\Goods\Stock;
use App\Models\Helpers\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fill image_id if id null, and stocks have images (to be run after trade units set up or the first image added)
 */
class StockInitialiseImageID implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Stock $stock): string
    {
        return $stock->id;
    }

    public function handle(Stock $stock): void
    {
        if ($stock->images()->count()) {
            if ($stock->image_id) {
                return;
            }

            /** @var Media $image */
            $image = $stock->images()->first();

            if ($image) {
                $stock->update(
                    [
                        'image_id' => $image->id
                    ]
                );
            }
        }
    }

}
