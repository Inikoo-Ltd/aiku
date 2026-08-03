<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\RetinaAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryAllegro extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var AllegroUser $allegroUser */
        $allegroUser = $customerSalesChannel->user;

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
            $allegroUser->updateOffer($portfolio->platform_product_id, [
                'stock' => [
                    'available' => $availableQuantity,
                    'unit'      => 'UNIT'
                ]
            ]);

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
                'response' => 'E1: ' . $e->getMessage()
            ]);

            $customerSalesChannel->update([
                'ban_stock_update_util' => now()->addSeconds(10)
            ]);

            $portfolio->update([
                'stock_last_fail_updated_at' => now()
            ]);
        }
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($portfolio);
    }
}
