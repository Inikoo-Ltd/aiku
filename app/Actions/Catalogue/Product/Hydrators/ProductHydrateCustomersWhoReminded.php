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
use App\Models\Comms\BackInStockReminderSnapshot;
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
            'number_customers_who_reminded' => BackInStockReminderSnapshot::where('product_id', $product->id)->whereNull('reminder_cancelled_at')->whereNotNull('reminder_sent_at')->count(),
            'number_customers_who_un_reminded' => BackInStockReminderSnapshot::where('product_id', $product->id)->whereNotNull('reminder_cancelled_at')->whereNull('reminder_sent_at')->count(),
        ];

        $product->stats->update($stats);
    }

}
