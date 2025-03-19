<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jun 2024 10:36:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Fulfilment\MayaPalletReturnItemUIResource;

class NotPickedPalletFromReturn extends OrgAction
{
    use WithActionUpdate;


    private PalletReturnItem $palletReturnItem;

    public function handle(PalletReturnItem $palletReturnItem, $modelData): PalletReturnItem
    {
        if ($palletReturnItem->type == 'Pallet') {
            $palletReturnItem = $this->update($palletReturnItem, [
                'state' => PalletReturnItemStateEnum::NOT_PICKED
            ], ['data']);

            UpdatePallet::run($palletReturnItem->pallet, [
                'state'              => Arr::get($modelData, 'state'),
                'status'             => PalletStatusEnum::INCIDENT,
                'set_as_incident_at' => now(),
                'incident_report'    => [
                    'notes' => Arr::get($modelData, 'notes')
                ]
            ]);
        } else {
            $storedItems = PalletReturnItem::where('pallet_return_id', $palletReturnItem->pallet_return_id)->where('stored_item_id', $palletReturnItem->stored_item_id)->get();
            foreach ($storedItems as $storedItem) {
                $palletReturnItem = $this->update($storedItem, [
                    'state' => PalletReturnItemStateEnum::NOT_PICKED
                ], ['data']);

                UpdatePallet::run($storedItem->pallet, [
                    'state'              => Arr::get($modelData, 'state'),
                    'status'             => PalletStatusEnum::INCIDENT,
                    'set_as_incident_at' => now(),
                    'incident_report'    => [
                        'notes' => Arr::get($modelData, 'notes')
                    ]
                ]);
            }
        }

        AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($palletReturnItem->palletReturn);

        return $palletReturnItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }

    public function rules(): array
    {
        return [
            'state'   => ['required', Rule::enum(PalletStateEnum::class)],
            'notes'   => ['required', 'string']
        ];
    }

    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, $request);

        return $this->handle($palletReturnItem, $this->validatedData);
    }

    public function action(PalletReturnItem $palletReturnItem, $state, int $hydratorsDelay = 0): PalletReturnItem
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, []);

        return $this->handle($palletReturnItem, $this->validatedData);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItemUIResource|MayaPalletReturnItemUIResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletReturnItemUIResource::make($palletReturnItem);
        }
        return new PalletReturnItemUIResource($palletReturnItem);
    }
}
