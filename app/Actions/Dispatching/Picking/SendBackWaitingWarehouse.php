<?php

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SendBackWaitingWarehouse extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        // Disable waiting if setting is off
        if (!data_get($this->organisation->settings, 'orders.allow_waiting', false)) {
            abort(403, 'Waiting is not enabled for this organisation');
        }

        if ($deliveryNoteItem->has_waiting_crm) {
            throw ValidationException::withMessages([
                'message' => 'Unable to move back to Waiting Warehouse. Not a valid Waiting CRM item'
            ]);
        }
                
        $quantityToMove              = $deliveryNoteItem->quantity_waiting_crm;
        $newQuantityWaitingWarehouse = (($deliveryNoteItem->quantity_waiting_warehouse ?? 0) + $quantityToMove);

        $dataToUpdate = [
            'state'                      => DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
            'quantity_waiting_warehouse' => $newQuantityWaitingWarehouse,
            'quantity_waiting_crm'       => 0,
            'has_waiting_crm'            => false,
            'has_waiting_warehouse'      => $newQuantityWaitingWarehouse > 0,
        ];

        if (Arr::has($modelData, 'note')) {
            $dataToUpdate['notes'] = $modelData['note'];
        }

        $deliveryNoteItem->update(
            $dataToUpdate
        );

        DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);


        return $deliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'note'     => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user             = $request->user();
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): DeliveryNoteItem
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;

        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }


}
