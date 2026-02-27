<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateInventoryTiktokProducts
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:tiktok:product:inventory:update {customerSalesChannel}';

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $customerSalesChannel->user;

        foreach ($customerSalesChannel->portfolios as $portfolio) {
            /** @var Product $product */
            $product = $portfolio->item;

            $tiktokUser->updateProductInventory($portfolio->platform_product_id, [
                'skus' => [
                    'id' => Arr::get($portfolio, 'data.tiktok_product.skus.0.id', ''),
                    'inventory' => [
                        'quantity' => $product->available_quantity
                    ]
                ]
            ]);
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
