<?php

/*
 * author Louis Perez
 * created on 03-03-2026-17h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SetPalletBackToStoring extends OrgAction
{

    public function handle(Pallet $pallet, $modelData): Pallet
    {
        data_set($modelData, 'state', PalletStateEnum::STORING);
        data_set($modelData, 'status', PalletStatusEnum::STORING);
        data_set($modelData, 'set_as_incident_at', null); // Empty incident message ?
        data_set($modelData, 'incident_report', []); // Empty incident report message ?

        $pallet = UpdatePallet::run($pallet, Arr::except($modelData, 'message'));
        PalletRecordSearch::dispatch($pallet);

        return $pallet;
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

    public function action(Pallet $pallet, array $modelData): Pallet
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($pallet->warehouse, $modelData);

        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
