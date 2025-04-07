<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-11h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateCustomersWhoReminded implements ShouldBeUnique
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
            'number_customers_who_reminded' => $product->backInStockReminders()->whereNull('un_reminded_at')->count(),
            'number_customers_who_un_reminded' => $product->backInStockReminders()->whereNotNull('un_reminded_at')->count()
        ];

        $product->stats->update($stats);
    }

}
