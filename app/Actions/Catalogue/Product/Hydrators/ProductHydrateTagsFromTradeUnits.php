<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Sept 2024 17:15:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateTagsFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'product:hydrate-tags {product}';

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $tags = $product->tradeUnitTagsViaTradeUnits();

        $product->tags()->sync($tags->pluck('id'));
    }

    public function asCommand(Command $command): void
    {
        $product = Product::where('code', $command->argument('product'))->first();

        $this->handle($product);
    }
}
