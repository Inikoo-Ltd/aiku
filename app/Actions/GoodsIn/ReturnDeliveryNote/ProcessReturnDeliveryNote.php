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
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\UnidentifiedReturn;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class ProcessReturnDeliveryNote extends OrgAction
{
    use WithHydrateReturnDeliveryNotes;
    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): ReturnDeliveryNote
    {
        $returnDeliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            $returnDeliveryNote = StoreReturnDeliveryNote::make()->action($deliveryNote, []);
            $returnDeliveryNote->refresh();

            foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
                StoreReturnDeliveryNoteItems::make()->action($returnDeliveryNote, [
                    'delivery_note_items_id' => $deliveryNoteItem->id,
                ]);
            }

            $deliveryNote->update([
                'is_returned' => true
            ]);

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
        if ($this->deliveryNote->state !== DeliveryNoteStateEnum::DISPATCHED) {
            $validator->errors()->add('delivery_note', 'Unable to create return for this instance. Selected delivery note is invalid');
        }
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
