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
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateWooPortfolio
{
    use AsAction;

    public string $commandSignature = 'inventory-woo:bulk_update {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user, $customerSalesChannel->portfolios()->limit(101)->get());
    }

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
                $decodedMsg = [];
                if (is_string(Arr::get($stockUpdated, '0'))) {
                    $decodedMsg = json_decode(Arr::get($stockUpdated, '0'), true);
                }

                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => Arr::get($decodedMsg, 'message') ?? __('Unknown')
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
