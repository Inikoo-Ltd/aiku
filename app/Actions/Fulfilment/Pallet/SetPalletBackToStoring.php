<?php

/*
 * author Louis Perez
 * created on 03-03-2026-17h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsCancelledIfEmpty;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class SetPalletBackToStoring extends OrgAction
{

    public function handle(Pallet $pallet): Pallet
    {
        DB::transaction(function () use ($pallet) {
            $currPalletReturn = $pallet->palletReturn;
            
            data_set($modelData, 'state', PalletStateEnum::STORING);
            data_set($modelData, 'status', PalletStatusEnum::STORING);
            data_set($modelData, 'set_as_incident_at', null); // Empty incident message ?
            data_set($modelData, 'incident_report', []); // Empty incident report message ?
    
            $pallet = UpdatePallet::run($pallet, Arr::except($modelData, 'message'));
            $pallet->palletReturn()->dissociate(); // To empty the pallet.pallet_return_id
            $pallet->palletReturns()->syncWithoutDetaching([
                $currPalletReturn->id => ['state' => PalletReturnStateEnum::CANCEL]
            ]); // To declare it as cancelled in the relationship
            $pallet->save();
            
            if($currPalletReturn->pallets()->whereNot('pallet_id', $pallet->id)->whereNotIn('pallet_return_items.state', [PalletReturnStateEnum::DISPATCHED, PalletReturnStateEnum::CANCEL])->exists()){
                AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($currPalletReturn);
            }else{
                AutomaticallySetPalletReturnAsCancelledIfEmpty::run($currPalletReturn);
            }
            PalletReturnHydratePallets::run($currPalletReturn);
            
        });

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

    public function asController(Pallet $pallet, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);
        $this->handle($pallet);
        

        return redirect()->back();
    }

    public function action(Pallet $pallet, array $modelData): Pallet
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($pallet->warehouse, $modelData);

        return $this->handle($pallet);
    }
}
