<?php

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoWaitingDeliveryNote;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UndoWaitingPickingSession extends OrgAction
{
    /**
     * Steps a waiting session back to picking by stepping back each of its waiting delivery notes:
     * items waiting for the warehouse and parts of a set sold only complete that were not found go
     * back to the picker. A note with nothing left to pick stays waiting, and the session is only
     * refused when none of its notes can go back.
     *
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession): PickingSession
    {
        if ($pickingSession->state !== PickingSessionStateEnum::HANDLING_BLOCKED) {
            throw ValidationException::withMessages([
                'message' => __('This picking session is not waiting any more'),
            ]);
        }

        $steppedBack = 0;

        foreach ($pickingSession->deliveryNotes()->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->get() as $deliveryNote) {
            try {
                UndoWaitingDeliveryNote::make()->action($deliveryNote);
                $steppedBack++;
            } catch (ValidationException) {
                continue;
            }
        }

        if ($steppedBack === 0) {
            throw ValidationException::withMessages([
                'message' => __('Nothing would be left to pick. Items waiting for customer services have to be sent back to the warehouse from the waiting page first.'),
            ]);
        }

        return $pickingSession->refresh();
    }

    public static function canStepBack(?User $user, PickingSession $pickingSession): bool
    {
        if (!$user) {
            return false;
        }

        return (int)$user->id === (int)$pickingSession->user_id || $user->authTo([
            "supervisor-dispatching.$pickingSession->warehouse_id",
            "org-admin.$pickingSession->organisation_id",
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canStepBack($request->user(), $request->route('pickingSession'));
    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }
}
