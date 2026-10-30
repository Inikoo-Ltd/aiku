<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Wed, 12 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

/**
 * Remembers whether this delivery note is being picked with a scanner, so a refresh, a tab change or
 * a second person taking the note over lands on the same screen the picker left behind.
 *
 * The trolley and picker hydrators write other keys of the same jsonb column, so only this key is set.
 */
class SetScanToPickDeliveryNote extends OrgAction
{
    use WithDeliveryNoteHandler;

    private DeliveryNote $deliveryNote;

    public function authorize(ActionRequest $request): bool
    {
        if (!data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false)) {
            return false;
        }

        return $this->canHandleDeliveryNote($this->deliveryNote);
    }

    public function handle(DeliveryNote $deliveryNote, array $modelData): bool
    {
        $isOn = (bool)$modelData['scan_to_pick'];
        DeliveryNote::where('id', $deliveryNote->id)->update(['data->scan_to_pick' => $isOn ?: null]);
        $deliveryNote->refresh();

        return $isOn;
    }

    public static function isScanToPickOn(DeliveryNote $deliveryNote): bool
    {
        return (bool)data_get($deliveryNote->data, 'scan_to_pick', false);
    }

    public function rules(): array
    {
        return [
            'scan_to_pick' => ['required', 'boolean'],
        ];
    }

    public function jsonResponse(bool $isScanToPickOn): array
    {
        return [
            'scan_to_pick' => $isScanToPickOn,
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): bool
    {
        $this->deliveryNote = $deliveryNote;

        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
