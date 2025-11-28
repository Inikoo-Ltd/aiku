<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Dropshipping\DropshippingPortfoliosResource;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Log;

class UpdateRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;

    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio, array $modelData): Portfolio
    {
        $customerSalesChannel = $portfolio->customerSalesChannel;

        if ($customerSalesChannel) {
            $downloadPortfolioCustomerSalesChannels = DownloadPortfolioCustomerSalesChannel::where('customer_sales_channel_id', $customerSalesChannel->id)->whereNull('deleted_at')->get();

            $file_paths = $downloadPortfolioCustomerSalesChannels->pluck('file_path')->filter()->values()->toArray();
            $ids = $downloadPortfolioCustomerSalesChannels->pluck('id')->toArray();

            if (!empty($file_paths)) {
                try {
                    RemoveFilesFromCatalogueIrisR2::run($file_paths);
                } catch (\Exception $e) {
                    Log::error('Failed to remove files from R2: ' . $e->getMessage());
                }
            }

            if (!empty($ids)) {
                try {
                    DownloadPortfolioCustomerSalesChannel::whereIn('id', $ids)->delete();
                } catch (\Exception $e) {
                    Log::error('Failed to delete download records: ' . $e->getMessage());
                }
            }
        }
        return UpdatePortfolio::make()->action($portfolio, $modelData);
    }

    public function jsonResponse(Portfolio $portfolio): array
    {
        return DropshippingPortfoliosResource::make($portfolio)->resolve();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->portfolio->customer_id == $this->customer->id;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request)
    {
        $this->portfolio = $portfolio;
        $this->initialisation($request);

        $this->handle($portfolio, $request->all());
    }
}
