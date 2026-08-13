<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Wed, 12 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

/**
 * Remembers whether this delivery note is being picked with a scanner, so a refresh, a tab change or
 * a second person taking the note over lands on the same screen the picker left behind.
 *
 * The trolley and picker hydrators write the same jsonb column, so this takes the lock they share
 * rather than overwriting whatever they put there between the read and the write.
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
        $lock = Cache::lock('delivery_note_data_update_'.$deliveryNote->id, 10);

        try {
            $lock->block(5, function () use ($deliveryNote, $isOn) {
                $data = $deliveryNote->data ?? [];

                if ($isOn) {
                    $data['scan_to_pick'] = true;
                } else {
                    unset($data['scan_to_pick']);
                }

                $deliveryNote->update(['data' => $data]);
            });
        } catch (LockTimeoutException) {
            return static::isScanToPickOn($deliveryNote->refresh());
        }

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
