<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreFulfilmentPickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, array $modelData): PickingSession
    {
        return DB::transaction(function () use ($warehouse, $modelData) {
            $palletReturnIds = Arr::wrap(Arr::pull($modelData, 'pallet_returns', []));
            $palletReturnIds = array_values(array_unique($palletReturnIds));

            $validPalletReturnIds = PalletReturn::query()
                ->whereIn('id', $palletReturnIds)
                ->whereIn('state', [
                    PalletReturnStateEnum::CONFIRMED,
                    PalletReturnStateEnum::SUBMITTED
                ])
                ->whereDoesntHave('pickingSessions')
                ->pluck('id')
                ->all();

            $invalidIds = array_values(array_diff($palletReturnIds, $validPalletReturnIds));

            if (count($invalidIds) > 0) {
                $invalidReferences = PalletReturn::query()
                    ->whereIn('id', $invalidIds)
                    ->pluck('reference')
                    ->all();

                throw ValidationException::withMessages(
                    [
                        'message' => [
                            'pallet_returns' => __('Some selected pallet returns are already in a picking session or invalid: :refs', [
                                'refs' => implode(', ', $invalidReferences),
                            ]),
                        ]
                    ]
                );
            }

            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $warehouse->organisation,
                    modelType: SerialReferenceModelEnum::PICKING_SESSION,
                )
            );

            data_set($modelData, 'state', PickingSessionStateEnum::IN_PROCESS->value);
            data_set($modelData, 'type', PickingSessionTypeEnum::FULFILMENT->value);

            $user = request()->user() ?? ($modelData['user_id'] ? User::find($modelData['user_id']) : null);
            data_set($modelData, 'user_id', $user?->id);

            data_set($modelData, 'group_id', $warehouse->group_id);
            data_set($modelData, 'organisation_id', $warehouse->organisation_id);

            /** @var PickingSession $pickingSession */
            $pickingSession = $warehouse->pickingSessions()->create($modelData);

            // Attach Pallet Returns
            $pickingSession->palletReturns()->attach($validPalletReturnIds, [
                'organisation_id' => $pickingSession->organisation_id,
                'group_id'        => $pickingSession->group_id
            ]);

            $pickingSession->refresh();

            $palletReturns = $pickingSession->palletReturns;

            $numberItems         = 0;
            $numberPalletReturns = 0;

            foreach ($palletReturns as $palletReturn) {
                if ($palletReturn->state == PalletReturnStateEnum::CANCEL) {
                    continue;
                }

                $numberPalletReturns++;

                // Update Pallet Return Items
                foreach ($palletReturn->items as $item) {
                    $numberItems++;
                    $item->updateQuietly([
                        'picking_session_id' => $pickingSession->id
                    ]);
                }
            }

            $pickingSession->updateQuietly([
                'number_items'          => $numberItems,
                'number_pallet_returns' => $numberPalletReturns,
            ]);

            WarehouseHydratePickingSessions::dispatch($warehouse);

            return $pickingSession;
        });
    }

    public function rules(): array
    {
        return [
            'pallet_returns' => ['required', 'array'],
            'user_id'        => ['sometimes', 'exists:users,id'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picking_sessions.fulfilment.show', [
            'organisation'   => $pickingSession->organisation->slug,
            'warehouse'      => $pickingSession->warehouse->slug,
            'pickingSession' => $pickingSession->slug,
        ]);
    }
}
