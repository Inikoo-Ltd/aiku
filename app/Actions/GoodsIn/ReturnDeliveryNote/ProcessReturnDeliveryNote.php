<?php

/*
 * author Louis Perez
 * created on 28-04-2026-10h-09m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\StoreReturnDeliveryNoteItems;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\UnidentifiedReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class ProcessReturnDeliveryNote extends OrgAction
{
    use WithHydrateReturnDeliveryNotes;
    private DeliveryNote $deliveryNote;
    private ReturnDeliveryNoteTypeEnum $type = ReturnDeliveryNoteTypeEnum::RETURN;

    public function handle(DeliveryNote $deliveryNote, array $modelData): ReturnDeliveryNote
    {
        $returnDeliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            DeliveryNote::whereKey($deliveryNote->id)->lockForUpdate()->first();

            if ($deliveryNote->returnedDeliveryNote()->whereIn('state', [ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::RETURNING])->exists()) {
                throw ValidationException::withMessages(['delivery_note' => __('This delivery note already has a return in progress, finish or cancel it first.')]);
            }

            /**
             * A cancellation is raised on a note that never dispatched, so quantity_dispatched is 0
             * for every line and the dispatched filter would find nothing to put away. What is
             * physically off the shelf and needs walking back is quantity_picked.
             */
            $isCancellation = $this->type === ReturnDeliveryNoteTypeEnum::CANCELLATION;

            $returnableItems = $deliveryNote->deliveryNoteItems()->get()
                ->filter(function ($deliveryNoteItem) use ($isCancellation) {
                    if ($isCancellation) {
                        return (float)$deliveryNoteItem->quantity_picked > 0;
                    }

                    return (float)$deliveryNoteItem->quantity_dispatched - (float)($deliveryNoteItem->quantity_returned ?? 0) > 0;
                });

            if ($returnableItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'delivery_note' => $isCancellation
                        ? __('Nothing was picked in this delivery note, there is nothing to put back.')
                        : __('Everything dispatched in this delivery note has already been returned.')
                ]);
            }

            $returnDeliveryNote = StoreReturnDeliveryNote::make()->action(
                deliveryNote: $deliveryNote,
                modelData: [],
                type: $this->type
            );
            $returnDeliveryNote->refresh();

            foreach ($returnableItems as $deliveryNoteItem) {
                StoreReturnDeliveryNoteItems::make()->action($returnDeliveryNote, [
                    'delivery_note_items_id' => $deliveryNoteItem->id,
                ]);
            }

            /**
             * Cancelled and returned are different facts: the customer never received these goods,
             * so the note stays un-returned and dispatch reporting is not told otherwise.
             */
            if (!$isCancellation) {
                $deliveryNote->update([
                    'is_returned' => true
                ]);
            }

            if ($unidentifiedReturnId = Arr::get($modelData, 'unidentified_return_id')) {
                UnidentifiedReturn::where('id', $unidentifiedReturnId)->update([
                    'delivery_note_id'        => $deliveryNote->id,
                    'return_delivery_note_id' => $returnDeliveryNote->id,
                    'identified_at'           => now(),
                ]);
            }

            $returnDeliveryNote->refresh();

            return $returnDeliveryNote;
        });

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

    public function htmlResponse(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.incoming.return_delivery_notes.show', [
                'organisation' => $this->organisation,
                'warehouse' => $returnDeliveryNote->warehouse->slug,
                'returnDeliveryNote' => $returnDeliveryNote
            ])->with('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Return Delivery Note created successfully.'),
            ]);
    }

    public function rules(): array
    {
        return [
            'unidentified_return_id' => [
                'sometimes',
                'nullable',
                Rule::exists('unidentified_returns', 'id')
                    ->where('organisation_id', $this->organisation->id)
                    ->whereNull('identified_at'),
            ],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request)
    {
        $requiredState = $this->type === ReturnDeliveryNoteTypeEnum::CANCELLATION
            ? DeliveryNoteStateEnum::CANCELLED
            : DeliveryNoteStateEnum::DISPATCHED;

        if ($this->deliveryNote->state !== $requiredState) {
            $validator->errors()->add('delivery_note', 'Unable to create return for this instance. Selected delivery note is invalid');
        }
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    /**
     * Entry point for internal callers, used by CancelDeliveryNote to raise the put-away worklist
     * for goods that were picked but never dispatched.
     */
    public function action(DeliveryNote $deliveryNote, array $modelData = [], ReturnDeliveryNoteTypeEnum $type = ReturnDeliveryNoteTypeEnum::RETURN): ReturnDeliveryNote
    {
        $this->asAction     = true;
        $this->deliveryNote = $deliveryNote;
        $this->type         = $type;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
