<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncStoredItemPallet extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected FulfilmentCustomer $fulfilmentCustomer;
    protected Fulfilment $fulfilment;

    public function handle(StoredItem $storedItem, array $modelData): void
    {
        $newPallets = Arr::get($modelData, 'pallets', []);

        if (!$storedItem->state->canBeStored()) {
            $currentQuantities = DB::table('pallet_stored_items')
                ->where('stored_item_id', $storedItem->id)
                ->pluck('quantity', 'pallet_id');

            foreach ($newPallets as $palletId => $palletData) {
                if ($palletData['quantity'] > ($currentQuantities[$palletId] ?? 0)) {
                    throw ValidationException::withMessages(['pallets' => __('The SKU ":reference" is :state, its quantity cannot be increased.', ['reference' => $storedItem->reference, 'state' => $storedItem->state->labelGenerated()])]);
                }
            }
        }

        $storedItem->pallets()->sync($newPallets);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'pallets'            => ['sometimes', 'array'],
            'pallets.*.quantity' => ['required', 'integer', 'min:1']
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'pallets.*.quantity.required' => __('The quantity is required'),
            'pallets.*.quantity.integer'  => __('The quantity must be an integer'),
            'pallets.*.quantity.min'      => __('The quantity must be at least 1'),
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $pallets = [];
        foreach ($request->input('pallets') as $pallet) {
            $pallets[$pallet['pallet']] = [
                'quantity' => $pallet['quantity']
            ];
        }

        $this->set('pallets', $pallets);
    }

    public function asController(StoredItem $storedItem, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $storedItem->fulfilmentCustomer;
        $this->fulfilment         = $storedItem->fulfilment;

        $this->initialisation($storedItem->organisation, $request);

        $this->handle($storedItem, $this->validatedData);
    }
}
