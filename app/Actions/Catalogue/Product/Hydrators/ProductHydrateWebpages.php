<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-13h-14m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Product;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateWebpages
{
    use AsAction;
    use WithEnumStats;
    private Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->product->id))->dontRelease()];
    }
    public function handle(Product $product): void
    {

        $stats         = [
            'number_parent_webpages' => $product->webpageHasproducts()->count(),
        ];

        $product->stats->update($stats);
    }

}
