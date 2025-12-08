<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreRetinaPalletDelivery extends RetinaAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): PalletDelivery
    {
        return StorePalletDelivery::run($fulfilmentCustomer, $modelData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->fulfilment->warehouses()->first();
        $this->fill(['warehouse_id' => $warehouse->id]);
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
        ];
    }

    public function asController(ActionRequest $request): PalletDelivery
    {
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): PalletDelivery
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($fulfilmentCustomer, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): Response
    {
        return Redirect::route('retina.fulfilment.storage.pallet_deliveries.show', [
            'palletDelivery' => $palletDelivery->slug,
        ]);
    }
}
