<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateInventoryTiktokProducts
{
    use AsAction;
    use WithAttributes;

    public string $commandSignature = 'dropshipping:tiktok:product:inventory:update {customerSalesChannel?}';

    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::TIKTOK)->first();

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
            $platformStatus = false;

            if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
                continue;
            }

            if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                continue;
            }

            /** @var TiktokUser $tiktokUser */
            $tiktokUser = $customerSalesChannel->user;

            if (!$tiktokUser) {
                continue;
            }

            if (! $tiktokUser->tiktok_warehouse_id) {
                continue;
            }

            if (! $tiktokUser->tiktok_shop_id) {
                continue;
            }

            if (! $tiktokUser->tiktok_shop_chiper) {
                continue;
            }

            $tiktokShop = Arr::get($tiktokUser->getAuthorizedShop(), 'data.shops');

            if (Arr::get($tiktokShop, '0')) {
                $platformStatus = true;
            }

            if (!$platformStatus) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);

                continue;
            }

            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_id')
                ->where('item_type', 'Product')
                ->where('platform_status', true)
                ->get();

            foreach ($portfolios as $portfolio) {
                if ($this->checkIfApplicable($portfolio)) {
                    UpdateTiktokInventory::dispatch($portfolio, $customerSalesChannel)->delay(5);
                }
            }
        }
    }

    public function checkIfApplicable(Portfolio $portfolio): bool
    {
        $applicable = false;


        if (!$portfolio->stock_last_updated_at) {
            $applicable = true;
        } else {
            /** @var Product $product */
            $product = $portfolio->item;

            if (!$product->available_quantity_updated_at || $product->available_quantity_updated_at->gt($portfolio->stock_last_updated_at)) {
                $applicable = true;
            }
        }

        return $applicable;
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel);
    }
}
