<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInWixPortfolio
{
    use AsAction;

    public string $commandSignature = 'wix:update-inventory {customerSalesChannel?}';

    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platformIds = Platform::where('type', PlatformTypeEnum::WIX)->pluck('id');

        if ($customerSalesChannel === null) {
            $customerSalesChannels = CustomerSalesChannel::whereIn('platform_id', $platformIds)
                ->where('platform_status', true)
                ->where('stock_update', true)
                ->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::whereIn('platform_id', $platformIds)
                ->where('id', $customerSalesChannel->id)
                ->get();
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
                continue;
            }

            if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                continue;
            }

            /** @var WixUser $wixUser */
            $wixUser = $customerSalesChannel->user;

            if (!$wixUser) {
                continue;
            }

            try {
                $wixUser->getUserInfo();
            } catch (\Exception) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);

                continue;
            }

            $customerSalesChannel->update([
                'ban_stock_update_util' => null
            ]);

            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_id')
                ->where('item_type', 'Product')
                ->where('platform_status', true)
                ->get();

            $first = true;
            /** @var Portfolio $portfolio */
            foreach ($portfolios as $portfolio) {
                if (!$this->checkIfApplicable($portfolio)) {
                    continue;
                }

                if ($first) {
                    UpdateInventoryWix::run($portfolio);
                    $first = false;
                } else {
                    UpdateInventoryWix::dispatch($portfolio)->delay(now()->addSeconds(random_int(1, 120)));
                }
            }
        }
    }

    public function checkIfApplicable(Portfolio $portfolio): bool
    {
        if (!$portfolio->stock_last_updated_at) {
            return true;
        }

        /** @var Product $product */
        $product = $portfolio->item;

        return !$product->available_quantity_updated_at
            || $product->available_quantity_updated_at->gt($portfolio->stock_last_updated_at);
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
