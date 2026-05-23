<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:25:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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
    
    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): void
    {
        $itemIds = Arr::get($modelData, 'item_id');
        $items = Product::whereIn('id', $itemIds)->get();
        $customerSalesChannels = $customer->customerSalesChannels;
        $customerSalesChannelsIds = $customerSalesChannels->pluck('id');


        $existingPortfolios = Portfolio::whereIn('customer_sales_channel_id', $customerSalesChannelsIds)
                ->whereIn('item_id', $items->pluck('id'))
                ->where('item_type', 'Product')
                ->get()
                ->keyBy(fn ($p) => "$p->customer_sales_channel_id-$p->item_id");

        DB::transaction(function () use ($customerSalesChannels, $items, $existingPortfolios) {
            $customerSalesChannels->each(function ($customerSalesChannel) use ($items, $existingPortfolios) {
                $items->each(function ($item) use ($customerSalesChannel, $existingPortfolios) {
                    $compositeKey = $customerSalesChannel->id . '-' . $item->id;
                    if ($existingPortfolios->has($compositeKey)) {
                        /** @var Portfolio $portfolio */
                        $portfolio = $existingPortfolios->get($compositeKey);
                        if (!$portfolio->status) {
                            UpdatePortfolio::make()->action($portfolio, ['status' => true]);
                        }
                    } else {
                        StorePortfolio::make()->action($customerSalesChannel, $item, []);
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

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): void
    {
        $user = $request->user();
        if(!$user){
            abort(401);
        }

        $this->initialisation($request);

        $this->handle($user->customer, $this->validatedData);
    }
}
