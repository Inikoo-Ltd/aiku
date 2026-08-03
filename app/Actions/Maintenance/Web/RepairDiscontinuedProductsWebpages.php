<?php

/*
 * author Louis Perez
 * created on 22-06-2026-11h-08m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\CloseDiscontinuedWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class RepairDiscontinuedProductsWebpages
{
    use WithActionUpdate;

    public function handle(Product $product, Command $command): void
    {
        if ($product->webpage) {
            $result = CloseDiscontinuedWebpage::run($product->webpage);

            if (Arr::has($result, 'redirect_to')) {
                $command->info(sprintf(
                    'Redirecting discontinued product %s to %s',
                    $product->slug,
                    $result['redirect_to']
                ));
            } else {
                $command->error(sprintf(
                    'No redirect found for discontinued product %s. Error: %s',
                    $product->slug,
                    data_get($result, 'error', 'n/a error message')
                ));
                exit;
            }
        }
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
            ])
            ->whereHas('webpage', function ($q) {
                $q->where('state', WebpageStateEnum::LIVE);
            });

        $count = (clone $query)->count();

        $command->info("Found $count discontinued/in_process products with LIVE webpages.");

        $query->orderBy('id')
            ->chunk(500, function ($products) use ($command) {
                foreach ($products as $product) {

                    $webpage = $product->webpage;

                    $this->handle($product, $command);

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
