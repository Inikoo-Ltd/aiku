<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateCustomersWhoFavourited implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {

        $stats         = [
            'number_customers_who_favourited' => $product->favourites()->whereNull('unfavourited_at')->count(),
            'number_customers_who_un_favourited' => $product->favourites()->whereNotNull('unfavourited_at')->count()
        ];

        $product->stats->update($stats);
    }

}
