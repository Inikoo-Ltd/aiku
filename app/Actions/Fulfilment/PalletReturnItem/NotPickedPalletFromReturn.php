<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jun 2024 10:36:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PickingSession\AutoFinishPickingFulfilmentPickingSession;
use App\Actions\Fulfilment\PickingSession\CalculateFulfilmentPickingSessionPicks;
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
        $requestedNotPicked = Arr::get($modelData, 'quantity_not_picked');

        if ($palletReturnItem->type == 'Pallet') {
            $palletReturnItem = $this->update($palletReturnItem, [
                'state'               => PalletReturnItemStateEnum::NOT_PICKED,
                'quantity_picked'     => 0,
                'quantity_not_picked' => max(0, (float) $palletReturnItem->quantity_ordered - (float) $palletReturnItem->quantity_picked),
                'quantity_waiting_crm' => 0,
                'has_waiting_crm'      => false,
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
            $maxNotPicked = max(0, (float) $palletReturnItem->quantity_ordered - (float) $palletReturnItem->quantity_picked);
            $quantityNotPicked = is_numeric($requestedNotPicked)
                ? min(max(0, (float) $requestedNotPicked), $maxNotPicked)
                : $maxNotPicked;

            $palletReturnItem = $this->update($palletReturnItem, [
                'quantity_not_picked' => $quantityNotPicked,
                'quantity_waiting_crm' => 0,
                'has_waiting_crm'      => false,
            ], ['data']);
        }

        $palletReturn = $palletReturnItem->palletReturn;
        if ($palletReturn) {
            $palletReturn->update([
                'number_items_waiting_crm' => $palletReturn->items()->where('has_waiting_crm', true)->count(),
            ]);
        }

        if ($palletReturnItem->picking_session_id && $palletReturnItem->pickingSession) {
            (new CalculateFulfilmentPickingSessionPicks())->action($palletReturnItem->pickingSession);
            (new AutoFinishPickingFulfilmentPickingSession())->action($palletReturnItem->pickingSession);
        }

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
        $palletReturnItem = request()->route('palletReturnItem');
        $isPalletType = $palletReturnItem instanceof PalletReturnItem && $palletReturnItem->type == 'Pallet';

        return [
            'state'   => [Rule::requiredIf($isPalletType), Rule::enum(PalletStateEnum::class)],
            'notes'   => [Rule::requiredIf($isPalletType), 'string'],
            'quantity_not_picked' => ['nullable', 'numeric', 'min:0']
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
