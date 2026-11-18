<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-12h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Notifications\Work\WorkNotifier;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePickingSession extends OrgAction
{
    use WithDispatchingEditAuthorisation;
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession, array $modelData): PickingSession
    {
        $pickingSession = $this->update($pickingSession, $modelData);

        if ($pickingSession->wasChanged('user_id') && $pickingSession->user_id) {
            WorkNotifier::withoutDeliveryNotePickerNotices(fn () => $pickingSession->deliveryNotes()
                ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED])
                ->where(fn ($query) => $query->whereNull('delivery_notes.picker_user_id')->orWhere('delivery_notes.picker_user_id', '!=', $pickingSession->user_id))
                ->get()
                ->each(fn (DeliveryNote $deliveryNote) => UpdateDeliveryNote::make()->action($deliveryNote, ['picker_user_id' => $pickingSession->user_id])));
        }

        WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);

        return $pickingSession;
    }

    public function rules(): array
    {
        return [
            'state'  => ['sometimes', Rule::enum(PickingSessionStateEnum::class)],
            'user_id' => ['sometimes', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];

    }

    public function action(PickingSession $pickingSession, array $modelData): PickingSession
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($pickingSession->warehouse, $modelData);
        return $this->handle($pickingSession, $this->validatedData);

    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
