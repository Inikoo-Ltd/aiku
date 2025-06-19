<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Portfolio;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class DeleteIrisPortfolioFromAllChannels extends RetinaAction
{
    use WithActionUpdate;



    public function handle(Customer $customer, array $modelData): void
    {
        foreach ($customer->customerSalesChannels as $salesChannel) {
            $items = Portfolio::find(Arr::get($modelData, 'id'));


            foreach ($items as $item) {
                if ($salesChannel->portfolios()
                    ->where('item_id', $item->id)
                    ->where('item_type', $item->getMorphClass())
                    ->exists()) {
                    continue;
                }

                DeletePortfolio::make()->action($salesChannel, $item, []);
            }
        }
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => 'required|array|min:1',
            'portfolio_id.*' => 'required|integer|exists:portfolios,id'
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $this->validatedData);
    }
}
