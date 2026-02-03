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
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisPortfolioToAllChannels extends IrisAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;

    public function handle(Customer $customer, array $modelData): void
    {
        $itemIds = Arr::get($modelData, 'item_id');
        $items = Product::whereIn('id', $itemIds)->get();
        $custSalesChannels = $customer->customerSalesChannels;
        $custSalesChannelsIds = $custSalesChannels->pluck('id');

        $existingPortfolios = Portfolio::whereIn('item_id', $itemIds)
            ->whereIn('customer_sales_channel_id', $custSalesChannelsIds)
            ->get()
            ->keyBy(fn ($p) => "{$p->customer_sales_channel_id}-{$p->item_id}");


        $existingPortfolios = Portfolio::whereIn('customer_sales_channel_id', $custSalesChannelsIds)
                ->whereIn('item_id', $items->pluck('id'))
                ->where('item_type', 'Product')
                ->get()
                ->keyBy(fn ($p) => "{$p->customer_sales_channel_id}-{$p->item_id}");

        DB::transaction(function () use ($custSalesChannels, $items, $existingPortfolios) {
            $custSalesChannels->each(function ($custSalesChannel) use ($items, $existingPortfolios) {
                $items->each(function ($item) use ($custSalesChannel, $existingPortfolios) {
                    $compositeKey = $custSalesChannel->id . '-' . $item->id;
                    if ($existingPortfolios->has($compositeKey)) {
                        $portfolio = $existingPortfolios->get($compositeKey);
                        if (!$portfolio->status) {
                            UpdatePortfolio::make()->action($portfolio, ['status' => true]);
                        }
                    } else {
                        StorePortfolio::make()->action($custSalesChannel, $item, []);
                    }
                });
            });

        });
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|integer|exists:products,id'
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
