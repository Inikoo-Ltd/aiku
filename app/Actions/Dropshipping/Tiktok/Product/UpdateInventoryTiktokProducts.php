<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
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

    public $commandSignature = 'dropshipping:tiktok:product:inventory:update {customerSalesChannel}';

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
                /** @var Product $product */
                $product = $portfolio->item;

                if ($this->checkIfApplicable($portfolio)) {
                    $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

                    $availableQuantity = $product->available_quantity ?? 0;

                    if (!$product->is_for_sale) {
                        $availableQuantity = 0;
                    }

                    if ($customerSalesChannel->max_quantity_advertise > 0) {
                        $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
                    }

                    $tiktokInventory = $tiktokUser->updateProductInventory($portfolio->platform_product_id, [
                        'skus' => [
                            'id' => Arr::get($portfolio, 'data.tiktok_product.skus.0.id', ''),
                            'inventory' => [
                                'quantity' => $availableQuantity
                            ]
                        ]
                    ]);

                    if (count(Arr::get($tiktokInventory, 'data.errors', [])) === 0) {
                        UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                            'status' => PlatformPortfolioLogsStatusEnum::OK,
                            'last_stock_value' => $availableQuantity
                        ]);
                        $customerSalesChannel->update([
                            'ban_stock_update_util' => null
                        ]);

                        $portfolio->update([
                            'last_stock_value' => $availableQuantity,
                            'stock_last_updated_at' => now()
                        ]);
                    } else {
                        UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                            'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                            'response' => 'E1: ' . Arr::get($tiktokInventory, 'data.errors.0.message', [])
                        ]);

                        $customerSalesChannel->update([
                            'ban_stock_update_util' => now()->addSeconds(10)
                        ]);


                        $portfolio->update([
                            'stock_last_fail_updated_at' => now()
                        ]);
                    }
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
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
