<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 16:06:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateUniversalSearch implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }


    public function handle(Shop $shop): void
    {
        $shop->universalSearch()->updateOrCreate(
            [],
            [
                'sections'        => ['catalogue'],
                'haystack_tier_1' => trim($shop->code.' '.$shop->name),
            ]
        );
    }

}
