<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateWooPortfolio
{
    use AsAction;

    public function handle(WooCommerceUser $wooCommerceUser, Collection $portfolios): void
    {
        try {
            $productData = [];
            $logs = [];

            foreach ($portfolios as $portfolio) {
                $logs[] = StorePlatformPortfolioLog::run($portfolio, []);

                $product = $portfolio->item;

                $productData['update'][] =
                    [
                        "id" => $portfolio->platform_product_id,
                        "stock_quantity" => $product->available_quantity,
                    ];
            }

            $stockUpdated = $wooCommerceUser->batchUpdateWooCommerceProducts($productData);

            if (Arr::get($stockUpdated, 'update')) {
                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => __('Unknown')
                ]);
            }
        } catch (\Throwable $e) {
            $this->bulkUpdateLogs($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);
        }
    }

    public function bulkUpdateLogs(array $platformPortfolioLogs, array $modelData): void
    {
        foreach ($platformPortfolioLogs as $platformPortfolioLog) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, $modelData);
        }
    }
}
