<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Http\Resources\Fulfilment\MayaPalletResource;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class BookInPallet extends OrgAction
{
    use WithActionUpdate;

    public function handle(Pallet $pallet, array $modelData): Pallet
    {
        data_set($modelData, 'state', PalletStateEnum::BOOKED_IN);
        data_set($modelData, 'booked_in_at', now());
        data_set($modelData, 'set_as_not_received_at', null);

        $pallet             = $this->update($pallet, $modelData, ['data']);
        $pallet->refresh();

        PalletDeliveryHydratePallets::dispatch($pallet->palletDelivery);
        LocationHydratePallets::dispatch($pallet->location);
        PalletRecordSearch::dispatch($pallet);

        return $pallet;
    }

    public function rules(): array
    {
        return [
            'location_id' => [
                'sometimes',
                Rule::exists('locations', 'id')
                    ->where('warehouse_id', $this->warehouse->id),
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }



    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);
        return $this->handle($pallet, $this->validatedData);
    }

    public function usingLocationSlug(Pallet $pallet, Location $location, ActionRequest $request): Pallet
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);

        return $this->handle($pallet, [
            'location_id' => $location->id
        ]);
    }

    public function action(Pallet $pallet, array $modelData): Pallet
    {
        $this->asAction       = true;
        $this->initialisationFromWarehouse($pallet->warehouse, $modelData);
        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet, ActionRequest $request): PalletResource|MayaPalletResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletResource::make($pallet);
        }
        return new PalletResource($pallet);
    }
}
