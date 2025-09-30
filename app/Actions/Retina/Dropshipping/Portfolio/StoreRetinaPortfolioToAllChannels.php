<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaPortfolioToAllChannels extends RetinaAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): void
    {
        $customerSalesChannels = $this->customer->customerSalesChannels->where('platform_status', true)->get();

        foreach ($customerSalesChannels as $salesChannel) {
            /** @var Product $items */
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
            'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|integer|exists:products,id'
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->validatedData);
    }
}
