<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToMultiChannels extends IrisAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;

    public function handle(Customer $customer, array $modelData): void
    {
        $channels = $customer->customerSalesChannels()->whereIn('id', Arr::get($modelData, 'customer_sales_channel_ids'))->get();

        foreach ($channels as $salesChannel) {
            $items = Product::whereIn('id', Arr::get($modelData, 'item_id'))->get();

            foreach ($items as $item) {
                if ($salesChannel->portfolios()
                    ->where('item_id', $item->id)
                    ->where('item_type', $item->getMorphClass())
                    ->exists()) {
                    continue;
                }

                StorePortfolio::make()->action($salesChannel, $item, []);
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_sales_channel_ids' => 'required|array|min:1',
            'customer_sales_channel_ids.*' => 'required|integer|exists:customer_sales_channels,id',
            'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|integer|exists:products,id'
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $this->validatedData);
    }
}
