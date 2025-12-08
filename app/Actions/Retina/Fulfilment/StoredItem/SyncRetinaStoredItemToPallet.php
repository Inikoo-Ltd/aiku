<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\StoredItem;

use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Lorisleiva\Actions\ActionRequest;

class SyncRetinaStoredItemToPallet extends RetinaAction
{
    public function handle(Pallet $pallet, array $modelData): void
    {
        SyncStoredItemToPallet::run($pallet, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $request->route()->parameter('pallet')->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'stored_item_ids' => ['sometimes', 'array'],
            'stored_item_ids.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'stored_item_ids.*.quantity.required' => __('The quantity is required'),
            'stored_item_ids.*.quantity.integer' => __('The quantity must be an integer'),
            'stored_item_ids.*.quantity.min' => __('The quantity must be at least 1'),
        ];
    }

    public function asController(Pallet $pallet, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($pallet, $this->validatedData);
    }

    public function action(Pallet $pallet, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($pallet->fulfilmentCustomer, $modelData);

        $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
