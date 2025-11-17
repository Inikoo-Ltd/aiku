<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydrateStoredItems;
use App\Actions\OrgAction;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StoreStoredItemsToReturn extends OrgAction
{
    use AsCommand;

    public $commandSignature = 'stored-item:store-to-return {palletReturn}';
    private PalletReturn $parent;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $storedItemModels = Arr::get($modelData, 'stored_items');

        if (blank($storedItemModels)) {
            PalletReturnItem::where('pallet_return_id', $palletReturn->id)->delete();

            return $palletReturn;
        }

        PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->whereNotIn('stored_item_id', array_keys($storedItemModels))->delete();

        $storedItems = $palletReturn->fulfilmentCustomer->storedItems()
            ->whereIn('stored_items.id', array_keys($storedItemModels))
            ->get();

        /** @var StoredItem $storedItem */
        foreach ($storedItems as $storedItem) {
            $pallets                   = $storedItem->pallets;
            $requiredQuantity          = Arr::get($storedItemModels, $storedItem->id)['quantity'];
            $allocatedQuantity         = 0;
            $existingPalletReturnItems = PalletReturnItem::where('pallet_return_id', $palletReturn->id)
                ->where('stored_item_id', $storedItem->id)
                ->exists();

            if ($existingPalletReturnItems) {
                $this->deleteItems($palletReturn, $storedItem);
            }

            foreach ($pallets as $pallet) {
                $palletStoredItemQty = $pallet->storedItems
                    ->where('pivot.stored_item_id', $storedItem->id)
                    ->first()->pivot->quantity ?? 0;

                if ($allocatedQuantity < $requiredQuantity) {
                    $quantityToUse = min($palletStoredItemQty, $requiredQuantity - $allocatedQuantity);
                    $this->attach($palletReturn, $pallet, $storedItem, $quantityToUse);
                    $allocatedQuantity += $quantityToUse;
                }
            }
        }

        $palletReturn->refresh();

        PalletReturnHydratePallets::run($palletReturn);
        PalletReturnHydrateStoredItems::run($palletReturn);

        return $palletReturn;
    }

    public function attach(PalletReturn $palletReturn, Pallet $pallet, StoredItem $storedItem, $quantityToUse): void
    {
        $storedItem->palletReturns()->attach($palletReturn->id, [
            'stored_item_id'        => $storedItem->id,
            'pallet_id'             => $pallet->id,
            'pallet_stored_item_id' => $pallet->pivot->id,
            'quantity_ordered'      => $quantityToUse,
            'type'                  => 'StoredItem'
        ]);
    }

    protected function deleteItems(PalletReturn $palletReturn, StoredItem $storedItem): void
    {
        $existingPivotItems = PalletReturnItem::where('pallet_return_id', $palletReturn->id)
            ->where('stored_item_id', $storedItem->id)
            ->get();

        foreach ($existingPivotItems as $pivotItem) {
            $pivotItem->delete();
        }
    }



    public function rules(): array
    {
        return [
            'stored_items.*.quantity' => ['required', 'integer']
        ];
    }


    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->parent = $palletReturn;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function fromRetina(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $this->parent       = $palletReturn;
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request->get('website')->organisation, $request->except(['domain', 'website']));

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData, int $hydratorsDelay = 0): PalletReturn
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $palletReturn;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        return $this->handle($palletReturn, $this->validatedData);
    }


    public function asCommand(Command $command): int
    {
        $palletReturn = PalletReturn::where('reference', $command->argument('palletDelivery'))->firstOrFail();

        $this->handle($palletReturn, [
            'group_id'               => $palletReturn->group_id,
            'organisation_id'        => $palletReturn->organisation_id,
            'fulfilment_id'          => $palletReturn->fulfilment_id,
            'fulfilment_customer_id' => $palletReturn->fulfilment_customer_id,
            'warehouse_id'           => $palletReturn->warehouse_id,
            'slug'                   => now()->timestamp
        ]);

        echo "Pallet created from delivery: $palletReturn->reference\n";

        return 0;
    }


    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.pallet-return.stored_item.store' => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.show', [
                'organisation'       => $palletReturn->organisation->slug,
                'fulfilment'         => $palletReturn->fulfilment->slug,
                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                'palletReturn'       => $palletReturn->slug
            ]),
            default => Redirect::route('retina.fulfilment.storage.pallet_returns.show', [
                'palletReturn' => $palletReturn->slug
            ])
        };
    }
}
