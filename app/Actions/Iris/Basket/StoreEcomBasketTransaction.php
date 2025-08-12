<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Basket;

use App\Actions\Iris\Basket\StoreEcomBasket;
use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\CRM\Customer;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreEcomBasketTransaction extends IrisAction
{
    public function handle(Customer $customer, array $modelData): Transaction
    {
        $order = $customer->orderInBasket;

        if (!$order) {
            $order = StoreEcomBasket::make()->action($customer);
        }

        $historicAsset = HistoricAsset::find($modelData['historic_asset_id']);

        return StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'numeric', 'min:0'],
            'historic_asset_id' => ['required', Rule::exists('historic_assets', 'id')],
        ];
    }

    public function asController(ActionRequest $request): Transaction
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
