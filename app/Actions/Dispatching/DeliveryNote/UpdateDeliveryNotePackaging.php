<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Billables\Packaging;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNotePackaging extends OrgAction
{
    use WithActionUpdate;
    use WithDeliveryNotePackaging;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        return $this->update($deliveryNote, $modelData);
    }

    public function rules(): array
    {
        return [
            'packaging_id' => [
                'sometimes',
                'nullable',
                Rule::exists('packagings', 'id')
                    ->where('shop_id', $this->deliveryNote->shop_id)
                    ->where('state', PackagingStateEnum::ACTIVE->value),
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->deliveryNote->shop?->hasPackagingAndInserts()) {
            $validator->errors()->add(
                'packaging_id',
                __('Packaging & inserts is not enabled for this shop.')
            );

            return;
        }

        if ($this->deliveryNote->state !== DeliveryNoteStateEnum::HANDLING) {
            $validator->errors()->add(
                'packaging_id',
                __('Packaging can only be changed while the delivery note is being picked.')
            );

            return;
        }

        $packagingId = $this->get('packaging_id');
        if (!$packagingId) {
            return;
        }

        $currentFamily = $this->deliveryNote->packaging?->family_code
            ?? $this->deliveryNote->orders()->first()?->packaging?->family_code;
        if (!$currentFamily) {
            return;
        }

        $new = Packaging::find($packagingId);
        if (!$new) {
            return;
        }

        if ($new->family_code !== $currentFamily) {
            $validator->errors()->add(
                'packaging_id',
                __('You can only change to another size within the same packaging family.')
            );

            return;
        }

        $paidPrice = $this->paidPackagingPrice($this->deliveryNote);

        if ($paidPrice !== null && round((float) $new->price, 2) !== $paidPrice) {
            $validator->errors()->add(
                'packaging_id',
                __('The order is already paid, so only a packaging costing the same (:paid) can be used. :name costs :price.', [
                    'paid'  => number_format($paidPrice, 2),
                    'name'  => $new->name,
                    'price' => number_format((float) $new->price, 2),
                ])
            );
        }
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function action(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $this->asAction    = true;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function htmlResponse(DeliveryNote $deliveryNote): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Packaging updated successfully.'),
        ]);
    }
}
