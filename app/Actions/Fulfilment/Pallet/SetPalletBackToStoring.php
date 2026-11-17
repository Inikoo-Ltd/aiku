<?php

/*
 * author Louis Perez
 * created on 03-03-2026-17h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsCancelledIfEmpty;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetPalletBackToStoring extends OrgAction
{
    public function handle(Pallet $pallet): Pallet
    {
        if ($pallet->status !== PalletStatusEnum::INCIDENT) {
            throw ValidationException::withMessages([
                'pallet' => __('Only a pallet with an incident can be set as usable again.'),
            ]);
        }

        return DB::transaction(function () use ($pallet) {
            $currPalletReturn = $pallet->palletReturn;

            $pallet = UpdatePallet::run($pallet, [
                'state'              => PalletStateEnum::STORING,
                'status'             => PalletStatusEnum::STORING,
                'set_as_incident_at' => null,
                'incident_report'    => [],
            ]);

            if (!$currPalletReturn) {
                return $pallet;
            }

            $pallet->palletReturn()->dissociate();
            $pallet->palletReturns()->syncWithoutDetaching([
                $currPalletReturn->id => ['state' => PalletReturnStateEnum::CANCEL]
            ]);
            $pallet->save();

            PalletReturnItem::where('pallet_return_id', $currPalletReturn->id)
                ->where('pallet_id', $pallet->id)
                ->update([
                    'quantity_waiting_crm' => 0,
                    'has_waiting_crm'      => false,
                ]);

            $currPalletReturn->update([
                'number_items_waiting_crm' => $currPalletReturn->items()->where('has_waiting_crm', true)->count(),
            ]);

            if (!$currPalletReturn->pallets()->whereNot('pallet_id', $pallet->id)->whereNotIn('pallet_return_items.state', [PalletReturnStateEnum::DISPATCHED, PalletReturnStateEnum::CANCEL])->exists()) {
                AutomaticallySetPalletReturnAsCancelledIfEmpty::run($currPalletReturn);
            }

            PalletReturnHydratePallets::run($currPalletReturn);

            return $pallet;
        });
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
