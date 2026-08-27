<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInEbayPortfolio
{
    use AsAction;

    public string $commandSignature = 'ebay:update-inventory {customerSalesChannel?}';


    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();

        if ($customerSalesChannel === null) {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('platform_status', true)
                ->where('stock_update', true)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->where(function ($query) {
                    $query->whereNull('ban_stock_update_util')
                        ->orWhere('ban_stock_update_util', '<=', now());
                })
                ->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('id', $customerSalesChannel->id)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->get();
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            /** @var \App\Models\Dropshipping\EbayUser $ebayUser */
            $ebayUser = $customerSalesChannel->user;

            if (!$ebayUser) {
                continue;
            }

            $ebayUser->setTimeout(20);
            $result = $ebayUser->getUser();

            if (!$result || !Arr::has($result, 'username')) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);

                continue;
            }

            if ($customerSalesChannel->ban_stock_update_util !== null) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);
            }

            Portfolio::query()
                ->select([
                    'id',
                    'item_id',
                    'item_type',
                    'last_stock_value',
                    'stock_last_updated_at',
                    'stock_last_fail_updated_at',
                ])
                ->where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_id')
                ->where('item_type', 'Product')
                ->where('platform_status', true)
                ->with('item:id,available_quantity,is_for_sale,is_bundle,available_quantity_updated_at')
                ->chunkById(500, function ($portfolioChunk) use ($customerSalesChannel): void {
                    /** @var Portfolio $portfolio */
                    foreach ($portfolioChunk as $portfolio) {
                        if ($this->checkIfApplicable($portfolio, $customerSalesChannel)) {
                            $delaySeconds = random_int(1, 120);
                            UpdateEbayPortfolio::dispatch($portfolio->id)->delay(now()->addSeconds($delaySeconds));
                        }
                    }
                });
        }
    }

    public function checkIfApplicable(Portfolio $portfolio, CustomerSalesChannel $customerSalesChannel): bool
    {
        $product = $portfolio->item;

        if (!$product instanceof Product) {
            return false;
        }

        $lastSuccessAt = $portfolio->stock_last_updated_at;
        $lastFailAt = $portfolio->stock_last_fail_updated_at;

        if ($lastFailAt && (!$lastSuccessAt || $lastFailAt->gt($lastSuccessAt))) {
            return $lastFailAt->lt(now()->subDay())
                || ($product->available_quantity_updated_at && $product->available_quantity_updated_at->gt($lastFailAt));
        }

        if (!$lastSuccessAt) {
            return true;
        }

        return (int) $portfolio->last_stock_value !== UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel);
    }


    public function asCommand(Command $command): void
    {
        $customerSalesChannel = null;

        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        }

        $this->handle($customerSalesChannel);
    }
}
