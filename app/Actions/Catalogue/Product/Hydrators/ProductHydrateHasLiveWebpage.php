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
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateHasLiveWebpage implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:product-has-live-webpage {--s|slug=}';

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function asCommand(Command $command): void
    {
        $slug = $command->option('slug');

        if (! $slug) {
            return;
        }

        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            return;
        }

        $this->handle($product);
    }

    public function handle(Product $product): void
    {
        $product->update(['has_live_webpage' => $product->webpage()->where('state', WebpageStateEnum::LIVE)->exists()]);
    }

}
