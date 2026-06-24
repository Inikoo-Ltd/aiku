<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AddPalletReturnsToPickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PickingSession $pickingSession, array $palletReturnIds): PickingSession
    {
        return DB::transaction(function () use ($pickingSession, $palletReturnIds) {
            $existingType = $pickingSession->palletReturns()->toBase()->value('type');

            $validPalletReturnIds = PalletReturn::query()
                ->whereIn('id', $palletReturnIds)
                ->whereIn('state', [
                    PalletReturnStateEnum::CONFIRMED,
                    PalletReturnStateEnum::SUBMITTED,
                ])
                ->when(is_string($existingType) && $existingType !== '', function ($query) use ($existingType) {
                    $query->where('type', $existingType);
                })
                ->whereDoesntHave('pickingSessions')
                ->pluck('id')
                ->all();

            if (count($validPalletReturnIds) === 0) {
                throw ValidationException::withMessages([
                    'message' => [
                        'pallet_returns' => __('No valid pallet returns found to add into this picking session.'),
                    ],
                ]);
            }

            $pickingSession->palletReturns()->attach($validPalletReturnIds, [
                'organisation_id' => $pickingSession->organisation_id,
                'group_id'        => $pickingSession->group_id,
            ]);

            $palletReturns = PalletReturn::query()
                ->whereIn('id', $validPalletReturnIds)
                ->with('items')
                ->get();

            $numberItems = 0;
            $numberPalletReturns = 0;

            foreach ($palletReturns as $palletReturn) {
                if ($palletReturn->state === PalletReturnStateEnum::CANCEL) {
                    continue;
                }

                $numberPalletReturns++;

                foreach ($palletReturn->items as $item) {
                    $numberItems++;
                    $item->updateQuietly([
                        'picking_session_id' => $pickingSession->id,
                    ]);
                }
            }

            $pickingSession->updateQuietly([
                'number_items'          => $pickingSession->number_items + $numberItems,
                'number_pallet_returns' => $pickingSession->number_pallet_returns + $numberPalletReturns,
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
