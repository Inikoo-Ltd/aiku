<?php

/*
 * author Louis Perez
 * created on 22-06-2026-11h-08m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Traits\WithManageWebpageState;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;

class RepairDiscontinuedProductsWebpages
{
    use WithActionUpdate;
    use WithManageWebpageState;

    public function handle(Product $product): void
    {
        $this->handleWebpageState($product);
    }

    public string $commandSignature = 'repair:discontinued-product-webpages';

    public function asCommand(Command $command): void
    {
        $query = Product::query()
            ->with(['webpage', 'shop'])
            ->whereHas('shop', function ($q) {
                $q->where('is_aiku', true);
            })
            ->whereIn('state', [
                ProductStateEnum::DISCONTINUED,
                ProductStateEnum::IN_PROCESS,
            ])
            ->whereHas('webpage', function ($q) {
                $q->where('state', WebpageStateEnum::LIVE);
            });

        $count = (clone $query)->count();

        $command->info("Found {$count} discontinued/in_process products with LIVE webpages.");

        $query->orderBy('id')
            ->chunk(500, function ($products) use ($command) {
                foreach ($products as $product) {

                    $webpage = $product->webpage;

                    $this->handle($product);

                    $command->info(sprintf(
                        "Repaired product: %s | product_state: %s | webpage: %s | webpage_state: %s",
                        $product->slug,
                        $product->state?->value ?? 'N/A',
                        $webpage->slug,
                        $webpage?->state?->value ?? 'N/A'
                    ));
                }
            });

        $command->info("Repair completed.");
    }
}
