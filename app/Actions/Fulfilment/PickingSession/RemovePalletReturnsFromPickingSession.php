<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RemovePalletReturnsFromPickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PickingSession $pickingSession, array $palletReturnIds): PickingSession
    {
        return DB::transaction(function () use ($pickingSession, $palletReturnIds) {
            $existingPalletReturns = $pickingSession->palletReturns()
                ->whereIn('pallet_returns.id', $palletReturnIds)
                ->with('items')
                ->get();

            if ($existingPalletReturns->isEmpty()) {
                throw ValidationException::withMessages([
                    'message' => [
                        'pallet_returns' => __('No pallet returns found in this picking session.'),
                    ],
                ]);
            }

            $numberItems = 0;
            $numberPalletReturns = $existingPalletReturns->count();

            foreach ($existingPalletReturns as $palletReturn) {
                foreach ($palletReturn->items as $item) {
                    if ($item->picking_session_id === $pickingSession->id) {
                        $numberItems++;
                        $item->updateQuietly([
                            'picking_session_id' => null,
                        ]);
                    }
                }
            }

            $pickingSession->palletReturns()->detach($palletReturnIds);

            $pickingSession->updateQuietly([
                'number_items'          => max(0, $pickingSession->number_items - $numberItems),
                'number_pallet_returns' => max(0, $pickingSession->number_pallet_returns - $numberPalletReturns),
            ]);

            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);

            return $pickingSession->refresh();
        });
    }

    public function rules(): array
    {
        return [
            'pallet_returns' => ['required', 'array'],
        ];
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData['pallet_returns']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
