<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateTiktokInventory implements ShouldBeUnique
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'tiktok';

    public function getJobUniqueId(Portfolio $portfolio, CustomerSalesChannel $customerSalesChannel): string
    {
        return $portfolio->id;
    }

    public static function quantityToSend(Product $product, CustomerSalesChannel $customerSalesChannel): int
    {
        $availableQuantity = $product->available_quantity ?? 0;

        if (!$product->is_for_sale) {
            $availableQuantity = 0;
        }

        if ($customerSalesChannel->stock_threshold > 0 && $availableQuantity <= $customerSalesChannel->stock_threshold) {
            return 0;
        }

        if ($customerSalesChannel->max_quantity_advertise > 0) {
            $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
        }

        return (int) $availableQuantity;
    }

    public function handle(Portfolio $portfolio, CustomerSalesChannel $customerSalesChannel): void
    {
        $product = $portfolio->item;

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $customerSalesChannel->user;

        if (!$product instanceof Product || !$tiktokUser instanceof TiktokUser) {
            return;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $availableQuantity = self::quantityToSend($product, $customerSalesChannel);

        $tiktokInventory = $tiktokUser->updateProductInventory($portfolio->platform_product_id, [
            'skus' => [
                [
                    'id' => Arr::get($portfolio, 'data.tiktok_product.skus.0.id', ''),
                    'inventory' => [
                        [
                            'warehouse_id' => (string) $tiktokUser->tiktok_warehouse_id,
                            'quantity' => $availableQuantity
                        ]
                    ]
                ]
            ]
        ]);

        if (count(Arr::get($tiktokInventory, 'data.errors', [])) === 0) {
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
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
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
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
