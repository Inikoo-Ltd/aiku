<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\RetinaAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryWix extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var WixUser $wixUser */
        $wixUser = $customerSalesChannel->user;

        /** @var Product $product */
        $product = $portfolio->item;

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $availableQuantity = $product->available_quantity ?? 0;

        if (!$product->is_for_sale) {
            $availableQuantity = 0;
        }

        if ($customerSalesChannel->max_quantity_advertise > 0) {
            $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
        }

        try {
            $inventoryItemId = $this->getInventoryItemId($wixUser, $portfolio);

            if (!$inventoryItemId) {
                throw new \Exception('Wix inventory item not found for product '.$portfolio->platform_product_id);
            }

            $response = $wixUser->updateInventoryVariants($inventoryItemId, [
                [
                    'variantId' => '00000000-0000-0000-0000-000000000000',
                    'inStock'   => $availableQuantity > 0,
                    'quantity'  => $availableQuantity,
                ]
            ]);

            if ($message = Arr::get($response, 'message')) {
                throw new \Exception($message);
            }

            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                'status'           => PlatformPortfolioLogsStatusEnum::OK,
                'last_stock_value' => $availableQuantity
            ]);

            $customerSalesChannel->update([
                'ban_stock_update_util' => null
            ]);

            $portfolio->update([
                'last_stock_value'      => $availableQuantity,
                'stock_last_updated_at' => now()
            ]);
        } catch (\Exception $e) {
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E1: '.$e->getMessage()
            ]);

            $customerSalesChannel->update([
                'ban_stock_update_util' => now()->addSeconds(10)
            ]);

            $portfolio->update([
                'stock_last_fail_updated_at' => now()
            ]);
        }
    }

    /**
     * The inventory item id is stable per product, so it is cached on the portfolio rather than
     * looked up on every stock push.
     */
    private function getInventoryItemId(WixUser $wixUser, Portfolio $portfolio): ?string
    {
        $inventoryItemId = Arr::get($portfolio->data, 'wix_inventory_item_id');

        if ($inventoryItemId) {
            return $inventoryItemId;
        }

        $inventoryItemId = $wixUser->getInventoryItemIdForProduct($portfolio->platform_product_id);

        if ($inventoryItemId) {
            $data = $portfolio->data;
            data_set($data, 'wix_inventory_item_id', $inventoryItemId);
            $portfolio->update(['data' => $data]);
        }

        return $inventoryItemId;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio);
    }
}
