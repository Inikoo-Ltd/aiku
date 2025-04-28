<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\Fulfilment\PalletStoredItem\SetPalletStoredItemStateToReturned;
use App\Actions\Fulfilment\StoredItemMovement\StoreStoredItemMovementFromPickingAFullPallet;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Http\Resources\Fulfilment\MayaPalletReturnItemUIResource;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class PickWholePalletInPalletReturn extends OrgAction
{
    use WithActionUpdate;


    private PalletReturnItem $pallet;

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        return DB::transaction(function () use ($palletReturnItem) {
            $modelData = [];
            data_set($modelData, 'picking_location_id', $palletReturnItem->pallet->location_id);
            data_set($modelData, 'state', PalletReturnItemStateEnum::PICKED);

            if ($palletReturnItem->type == 'Pallet') {
                $this->update($palletReturnItem, $modelData);
            } else {
                $storedItems = PalletReturnItem::where('pallet_return_id', $palletReturnItem->pallet_return_id)
                    ->where('stored_item_id', $palletReturnItem->stored_item_id)
                    ->get();
                foreach ($storedItems as $storedItem) {
                    $this->update($storedItem, $modelData);
                }
            }

            $modelData = [];
            data_set($modelData, 'state', PalletStateEnum::PICKED);
            data_set($modelData, 'status', PalletStatusEnum::RETURNING);
            data_set($modelData, 'picked_at', now());

            $pallet = UpdatePallet::run(
                pallet: $palletReturnItem->pallet,
                modelData: $modelData,
                hydrateParents: false
            );

            foreach ($pallet->palletStoredItems as $palletStoredItem) {
                StoreStoredItemMovementFromPickingAFullPallet::run($palletReturnItem, $palletStoredItem);
                SetPalletStoredItemStateToReturned::run($palletStoredItem);
            }

            PalletReturnHydratePallets::dispatch($palletReturnItem->palletReturn);
            PalletRecordSearch::dispatch($pallet);

            return $palletReturnItem;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * @throws \Throwable
     */
    public function fromRetina(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;
        $this->pallet       = $palletReturnItem;

        $this->initialisation($request->get('website')->organisation, $request);

        return $this->handle($palletReturnItem);
    }

    /**
     * @throws \Throwable
     */
    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->pallet = $palletReturnItem;
        $this->initialisationFromFulfilment($palletReturnItem->palletReturn->fulfilment, $request);

        return $this->handle($palletReturnItem);
    }


    /**
     * @throws \Throwable
     */
    public function action(PalletReturnItem $palletReturnItem, array $modelData, int $hydratorsDelay = 0): PalletReturnItem
    {
        $this->pallet         = $palletReturnItem;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($palletReturnItem->palletReturn->fulfilment, $modelData);

        return $this->handle($palletReturnItem);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItemUIResource|MayaPalletReturnItemUIResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletReturnItemUIResource::make($palletReturnItem);
        }

        return new PalletReturnItemUIResource($palletReturnItem);
    }
}
