<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-11h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Actions\Retina\Dropshipping\Portfolio\RemoveFilesFromCatalogueIrisR2;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;
use Illuminate\Support\Facades\Log;

class StoreRetinaProductManual extends RetinaAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
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

        DB::transaction(function () use ($customerSalesChannel, $modelData) {
            StoreMultiplePortfolios::run($customerSalesChannel, $modelData);
        });
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        $this->initialisationActions($customerSalesChannel->customer, $modelData);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
