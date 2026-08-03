<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInAllegroPortfolio
{
    use AsAction;

    public string $commandSignature = 'allegro:update-inventory {customerSalesChannel?}';

    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::ALLEGRO)->first();

        if ($customerSalesChannel === null) {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('platform_status', true)
                ->where('stock_update', true)
                ->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
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

            /** @var AllegroUser $allegroUser */
            $allegroUser = $customerSalesChannel->user;

            if (!$allegroUser) {
                continue;
            }

            try {
                $allegroUser->getUserInfo();
            } catch (\Exception $e) {
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
                    UpdateInventoryAllegro::run($portfolio);
                    $first = false;
                } else {
                    $delaySeconds = random_int(1, 120);
                    UpdateInventoryAllegro::dispatch($portfolio)->delay(now()->addSeconds($delaySeconds));
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
