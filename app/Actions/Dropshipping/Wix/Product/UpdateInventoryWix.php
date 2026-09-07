<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Wix\Traits\WithWixStockLevel;
use App\Actions\RetinaAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryWix extends RetinaAction
{
    use AsAction;
    use WithWixStockLevel;

    public function handle(Portfolio $portfolio): void
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var WixUser $wixUser */
        $wixUser = $customerSalesChannel->user;

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $availableQuantity = $this->wixStockLevel($portfolio);

        try {
            $response = $wixUser->catalog()->setInventory($portfolio->platform_product_id, $availableQuantity);

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

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio);
    }
}
