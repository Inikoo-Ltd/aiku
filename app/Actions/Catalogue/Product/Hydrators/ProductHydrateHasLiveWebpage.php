<?php

/*
 * author Louis Perez
 * created on 22-01-2026-14h-34m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateHasLiveWebpage implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $product->update(['has_live_webpage' => $product->webpage()->where('state', WebpageStateEnum::LIVE)->exists()]);
    }

}
