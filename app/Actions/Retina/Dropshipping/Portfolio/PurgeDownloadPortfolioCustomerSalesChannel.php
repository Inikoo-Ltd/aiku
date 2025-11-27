<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2025 17:06:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Exception;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;
use Illuminate\Support\Facades\Log;

class PurgeDownloadPortfolioCustomerSalesChannel
{
    use AsAction;

    public function handle()
    {
        try {
            $days = env('PURGE_DOWNLOAD_PORTFOLIO_CUSTOMER_SALES_CHANNEL_DAYS', 1);
            $downloadPortfolioCustomerSalesChannels = DownloadPortfolioCustomerSalesChannel::where('created_at', '<', now()->subDays($days))->whereNull('deleted_at')->get();
            $file_paths = $downloadPortfolioCustomerSalesChannels->pluck('file_path')->filter()->values()->toArray();
            $ids = $downloadPortfolioCustomerSalesChannels->pluck('id')->toArray();

            RemoveFilesFromCatalogueIrisR2::run($file_paths);

            DownloadPortfolioCustomerSalesChannel::whereIn('id', $ids)->delete();
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
