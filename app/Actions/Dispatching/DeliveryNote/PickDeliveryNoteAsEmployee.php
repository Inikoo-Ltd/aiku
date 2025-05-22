<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-09h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\HumanResources\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PickDeliveryNoteAsEmployee extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;
    public function handle(DeliveryNote $deliveryNote, Employee $employee): DeliveryNote
    {
        $deliveryNote = UpdateDeliveryNote::make()->action($deliveryNote, [
            'picker_id' => $employee->id,
        ]);

        data_set($modelData, 'queued_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            UpdateDeliveryNoteItem::make()->action($item, [
                'state' => DeliveryNoteItemStateEnum::HANDLING->value
            ]);
        }

        return $this->update($deliveryNote, $modelData);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $employee = $request->user()->employees()->first();

        return $this->handle($deliveryNote, $employee);
    }

    public function prepareForValidation()
    {
        $employee = request()->user()->employees()->first();
        if (!$employee) {
            throw ValidationException::withMessages([
                'messages' => __('You Are Not An Employee')
            ]);
        }
    }

    public function htmlResponse(DeliveryNote $deliveryNote): RedirectResponse
    {
        return Redirect::route(
            'grp.org.warehouses.show.dispatching.delivery-notes.show',
            [
            'organisation' => $deliveryNote->organisation->slug,
            'warehouse' => $deliveryNote->warehouse->slug,
            'deliveryNote' => $deliveryNote->slug
        ]
        );
    }

    public function action(DeliveryNote $deliveryNote, Employee $employee): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $employee);
    }
}
