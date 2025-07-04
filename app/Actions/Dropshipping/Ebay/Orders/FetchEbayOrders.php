<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\EbayUser;
use Lorisleiva\Actions\Concerns\AsCommand;

class FetchEbayOrders extends RetinaAction
{
    use AsCommand;

    public string $commandSignature = 'fetch:ebay-orders';

    public function asCommand(): void
    {
        $this->handle();
    }

    public function handle(): void
    {

        $ebayUsers=EbayUser::whereNotNull('customer_sales_channel_id')->get();
        foreach ($ebayUsers as $ebayUser) {
            FetchEbayUserOrders::dispatch($ebayUser);
        }


    }
}
