<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 20:27:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingEditAuthorisation;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class AttachTrolleyToPickingSession extends OrgAction
{
    use WithDispatchingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Trolley $trolley, PickingSession $pickingSession): Trolley
    {
        return DB::transaction(function () use ($trolley, $pickingSession) {
            $trolley = Trolley::lockForUpdate()->findOrFail($trolley->id);

            if ($trolley->picking_session_id === $pickingSession->id) {
                return $trolley;
            }

            if ($trolley->warehouse_id !== $pickingSession->warehouse_id) {
                throw ValidationException::withMessages([
                    'trolley' => __('Trolley :trolley is in another warehouse.', ['trolley' => $trolley->name]),
                ]);
            }

            if ($pickingSession->type === PickingSessionTypeEnum::FULFILMENT) {
                throw ValidationException::withMessages([
                    'trolley' => __('Trolleys are not given to fulfilment picking sessions.'),
                ]);
            }

            if ($pickingSession->state === PickingSessionStateEnum::PACKING_FINISHED) {
                throw ValidationException::withMessages([
                    'trolley' => __('This picking session is already packed.'),
                ]);
            }

            if ($trolley->picking_session_id) {
                throw ValidationException::withMessages([
                    'trolley' => __('Trolley :trolley is already used by picking session :session.', [
                        'trolley' => $trolley->name,
                        'session' => $trolley->pickingSession?->reference,
                    ]),
                ]);
            }

            if ($trolley->current_delivery_note_id) {
                throw ValidationException::withMessages([
                    'trolley' => __('Trolley :trolley is already used by delivery note :deliveryNote.', [
                        'trolley'      => $trolley->name,
                        'deliveryNote' => $trolley->deliveryNote?->reference,
                    ]),
                ]);
            }

            UpdateTrolley::run($trolley, [
                'picking_session_id' => $pickingSession->id,
            ]);

            $pickingSession->updateQuietly([
                'number_trolleys' => $pickingSession->trolleys()->count(),
            ]);

            return $trolley;
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($trolley, $pickingSession);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
