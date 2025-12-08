<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 14 Feb 2024 16:17:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaPalletReturn extends RetinaAction
{
    private bool $withStoredItems = false;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): PalletReturn
    {
        return StorePalletReturn::run($fulfilmentCustomer, $modelData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->fulfilment->warehouses()->count() == 1) {
            /** @var Warehouse $warehouse */
            $warehouse = $this->fulfilment->warehouses()->first();
            $this->fill(['warehouse_id' => $warehouse->id]);
        }

        if ($this->withStoredItems) {
            $this->set('type', PalletReturnTypeEnum::STORED_ITEM);
        } else {
            $this->set('type', PalletReturnTypeEnum::PALLET);
        }
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData, bool $withStoredItems = false): PalletReturn
    {
        $this->withStoredItems = $withStoredItems;
        $this->asAction = true;
        $this->initialisationFulfilmentActions($fulfilmentCustomer, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(PalletReturnTypeEnum::class)],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
            'customer_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(ActionRequest $request): PalletReturn
    {
        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer, $this->validatedData);
    }

    public function withStoredItems(ActionRequest $request): PalletReturn
    {
        $this->withStoredItems = true;
        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer, $this->validatedData);
    }

    public function jsonResponse(PalletReturn $palletReturn): array
    {
        if ($this->withStoredItems) {
            return [
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                    'parameters' => [
                        'palletReturn' => $palletReturn->slug,
                    ],
                ],
            ];
        }

        return [
            'route' => [
                'name' => 'retina.fulfilment.storage.pallet_returns.show',
                'parameters' => [
                    'palletReturn' => $palletReturn->slug,
                ],
            ],
        ];
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): RedirectResponse
    {
        if ($this->withStoredItems) {
            return Redirect::route('retina.fulfilment.storage.pallet_returns.with-stored-items.show', [
                'palletReturn' => $palletReturn->slug,
            ]);
        }

        return Redirect::route('retina.fulfilment.storage.pallet_returns.show', [
            'palletReturn' => $palletReturn->slug,
        ]);
    }
}
