<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026
 * Copyright (c) 2026, Inikoo
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ClearCustomerSalesChannelImagesZip
{
    use AsAction;

    public function handle(CustomerSalesChannel|int $customerSalesChannel): void
    {
        $customerSalesChannelId = $customerSalesChannel instanceof CustomerSalesChannel ? $customerSalesChannel->id : $customerSalesChannel;

        $downloads = DownloadPortfolioCustomerSalesChannel::where('customer_sales_channel_id', $customerSalesChannelId)->whereNull('deleted_at')->get();

        if ($downloads->isEmpty()) {
            return;
        }

        $filePaths = $downloads->pluck('file_path')->filter()->values()->toArray();

        if (!empty($filePaths)) {
            try {
                RemoveFilesFromCatalogueIrisR2::run($filePaths);
            } catch (Exception $e) {
                Log::error('Failed to remove files from R2: '.$e->getMessage());
            }
        }

        DownloadPortfolioCustomerSalesChannel::whereIn('id', $downloads->pluck('id'))->delete();
    }
}
