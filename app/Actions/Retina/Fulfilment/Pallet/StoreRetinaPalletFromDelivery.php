<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaPalletFromDelivery extends RetinaAction
{
    private PalletDelivery $parent;

    public function handle(PalletDelivery $palletDelivery, array $modelData): Pallet
    {
        return StorePalletCreatedInPalletDelivery::run($palletDelivery, $modelData);

    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->customer->id == $request->route()->parameter('palletDelivery')->fulfilmentCustomer->customer_id) {
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
            'type' => ['nullable', Rule::enum(PalletTypeEnum::class)],
            'customer_reference' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): Pallet
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $this->parent = $palletDelivery;
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
            'palletDelivery' => $this->parent->slug,
        ]);
    }

    public function action(PalletDelivery $palletDelivery, array $modelData): Pallet
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($palletDelivery->fulfilmentCustomer, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }
}
