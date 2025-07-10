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
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToMultiChannels extends IrisAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;
    /**
     * @var ProductCategory|null
     */
    private ?ProductCategory $productCategory;

    public function handle(Customer $customer, array $modelData): void
    {
        $channels = $customer->customerSalesChannels()
            ->whereIn('id', Arr::get($modelData, 'customer_sales_channel_ids'))
            ->get();

        $items = Product::whereIn('id', Arr::get($modelData, 'item_id'))
            ->get();

        $existingPortfolios = $channels->flatMap(function ($channel) use ($items) {
            return $channel->portfolios()
                ->whereIn('item_id', $items->pluck('id'))
                ->where('item_type', $items->first()->getMorphClass())
                ->get(['item_id', 'customer_sales_channel_id'])
                ->map(fn ($portfolio) => $portfolio->customer_sales_channel_id . '-' . $portfolio->item_id);
        })->unique()->values()->toArray();

        $channels->each(function ($salesChannel) use ($items, $existingPortfolios) {
            $items->chunk(100)->each(function ($chunkedItems) use ($salesChannel, $existingPortfolios) {
                $chunkedItems->each(function ($item) use ($salesChannel, $existingPortfolios) {
                    if (!in_array($salesChannel->id . '-' . $item->id, $existingPortfolios)) {
                        StorePortfolio::make()->action($salesChannel, $item, []);
                    }
                });
            });
        });
    }

    public function rules(): array
    {
        return [
            'customer_sales_channel_ids' => 'required|array|min:1',
            'customer_sales_channel_ids.*' => 'required|integer|exists:customer_sales_channels,id',
            // 'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|integer|exists:products,id'
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->productCategory) {
            $this->set('item_id', $this->productCategory->getProducts()->pluck('id')->toArray());
        }
    }

    public function asController(ActionRequest $request): void
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->productCategory = $productCategory;
        $customer = $request->user()->customer;
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
