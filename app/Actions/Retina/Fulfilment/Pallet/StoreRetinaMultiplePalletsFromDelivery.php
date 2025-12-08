<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\StoreMultiplePalletsFromDelivery;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaMultiplePalletsFromDelivery extends RetinaAction
{
    public function handle(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        StoreMultiplePalletsFromDelivery::run($palletDelivery, $modelData);
        $palletDelivery->refresh();

        return $palletDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        } elseif ($this->customer->id == $request->route()->parameter('palletDelivery')->fulfilmentCustomer->customer_id) {
            return true;
        }

        return false;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->fulfilment->warehouses()->count() == 1) {
            /** @var Warehouse $warehouse */
            $warehouse = $this->fulfilment->warehouses()->first();
            $this->fill(['warehouse_id' => $warehouse->id]);
        }
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'number_pallets' => ['required', 'integer', 'min:1', 'max:1000'],
            'type' => ['required', Rule::enum(PalletTypeEnum::class)],
        ];
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->initialisation($request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        $this->asAction = true;
        $this->fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
        $this->initialisationFulfilmentActions($this->fulfilmentCustomer, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function htmlResponse(PalletDelivery $palletDelivery): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
            'palletDelivery' => $palletDelivery->slug,
        ]);
    }
}
