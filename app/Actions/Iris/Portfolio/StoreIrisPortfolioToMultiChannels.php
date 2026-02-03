<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToMultiChannels extends IrisAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;
    /**
     * @var ProductCategory|null
     */
    private ?ProductCategory $productCategory = null;

    public function handle(Customer $customer, array $modelData): void
    {
        $channels = $customer->customerSalesChannels()
            ->whereIn('id', Arr::get($modelData, 'customer_sales_channel_ids'))
            ->get()
            ->keyBy('id');

        // Changed to only get Active Products; If is_for_sale is false / status is discontinued, will be ignored
        $items = Product::whereIn('id', Arr::get($modelData, 'item_id'))
            ->where('is_for_sale', true)
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->get();

        $existingPortfolios = Portfolio::whereIn('customer_sales_channel_id', $channels->keys())
                ->whereIn('item_id', $items->pluck('id'))
                ->where('item_type', 'Product')
                ->get()
                ->keyBy(fn ($p) => "{$p->customer_sales_channel_id}-{$p->item_id}");
        
        DB::transaction(function () use ($channels, $items, $existingPortfolios) {
            
            $channels->each(function ($custSalesChannel) use ($items, $existingPortfolios) {
                $items->each(function ($item) use ($custSalesChannel, $existingPortfolios) {
                    $compositeKey = $custSalesChannel->id . '-' . $item->id;
                    if($existingPortfolios->has($compositeKey)) {
                        $portfolio = $existingPortfolios->get($compositeKey);
                        if(!$portfolio->status){
                            UpdatePortfolio::make()->action($portfolio, ['status' => true]);
                        }
                    }else{
                        StorePortfolio::make()->action($custSalesChannel, $item, []);
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
