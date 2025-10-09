<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneFromChannelToChannel implements ShouldBeUnique
{
    use AsAction;

    public string $queue = 'long-running';


    public function getJobUniqueId(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel): string
    {
        return $fromChannel->id .'='. $toChannel->id;
    }

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $fromChannel, CustomerSalesChannel $toChannel): void
    {

        if ($fromChannel->id == $toChannel->id) {
            return;
        }

        if ($fromChannel->customer_id != $toChannel->customer_id) {
            return;
        }

        $items = $fromChannel->portfolios()->pluck('item_id')->toArray();

        StoreMultiplePortfolios::make()->action($toChannel, [
            'items' => $items
        ]);




    }


}
