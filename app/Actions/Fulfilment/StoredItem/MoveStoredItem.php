<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class MoveStoredItem extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected FulfilmentCustomer $fulfilmentCustomer;
    protected Fulfilment $fulfilment;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(StoredItem $storedItem, array $modelData): void
    {
        if (Arr::exists($modelData, 'pallet_id')) {
            $currentQuantity         = PalletStoredItem::where('pallet_id', $modelData['from_pallet_id'])->where('stored_item_id', $storedItem->id)->value('quantity') ?? 0;
            $toPalletCurrentQuantity = PalletStoredItem::where('pallet_id', $modelData['pallet_id'])->where('stored_item_id', $storedItem->id)->value('quantity')      ?? 0;

            $quantity = Arr::get($modelData, 'quantity', 1);

            if ($currentQuantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The quantity to move is greater than the current quantity']);
            }

            $storedItem->pallets()->syncWithoutDetaching([
                $modelData['pallet_id'] => [
                    'quantity' => $quantity + $toPalletCurrentQuantity,
                ],
                $modelData['from_pallet_id'] => [
                    'quantity' => $currentQuantity - $quantity
                ]
            ]);

            // TODO: Fix this action and call store stored item movement here (in and out)
        }
    }

    public function authorize(ActionRequest $request): bool
    {

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'location_id'      => ['sometimes', 'exists:locations,id'],
            'pallet_id'        => ['sometimes', 'exists:pallets,id'],
            'from_pallet_id'   => ['sometimes', 'exists:pallets,id'],
            'quantity'         => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(StoredItem $storedItem, ActionRequest $request): void
    {
        $this->fulfilmentCustomer = $storedItem->fulfilmentCustomer;
        $this->fulfilment         = $this->fulfilmentCustomer->fulfilment;

        $this->initialisation($storedItem->organisation, $request);

        $this->handle($storedItem, $this->validatedData);
    }

    public function jsonResponse(StoredItem $storedItem): StoredItemResource
    {
        return new StoredItemResource($storedItem);
    }
}
