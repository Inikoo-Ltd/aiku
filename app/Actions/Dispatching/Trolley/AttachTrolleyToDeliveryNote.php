<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 21:25:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteWorkAuthorisation;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use App\Actions\Audits\DispatchSimpleAudit;

class AttachTrolleyToDeliveryNote extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(Trolley $trolley, DeliveryNote $deliveryNote): void
    {
        // Get Old Trolley Event
        $oldTrolleys = $deliveryNote->trolleys()->pluck('name')->join(', ');

        $trolley->deliveryNotes()->attach([
            $deliveryNote->id => [
                'group_id'        => $deliveryNote->group_id,
                'organisation_id' => $deliveryNote->organisation_id
            ]
        ]);
        UpdateTrolley::run($trolley, [
            'current_delivery_note_id' => $deliveryNote->id
        ]);

        // Refresh Trolley Relation To Get New Event
        $deliveryNote->unsetRelation('trolleys');
        // Get New Event
        $newTrolleys = $deliveryNote->trolleys()->pluck('name')->join(', ');

        // Custom Audits Event
        DispatchSimpleAudit::run(
            auditableModel  : $deliveryNote,
            logKey          : 'trolleys_attached',
            oldValue        : $oldTrolleys,
            newValue        : $newTrolleys,
            eventName       : 'trolley_attached'
        );

        DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);
    }

    public function asController(DeliveryNote $deliveryNote, Trolley $trolley, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        if ($trolley->picking_session_id) {
            throw ValidationException::withMessages([
                'trolley' => __('Trolley :trolley is already used by picking session :session.', [
                    'trolley' => $trolley->name,
                    'session' => $trolley->pickingSession?->reference,
                ]),
            ]);
        }

        $this->handle($trolley, $deliveryNote);
    }


}
