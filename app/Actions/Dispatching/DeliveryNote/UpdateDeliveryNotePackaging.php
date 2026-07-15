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
        if ($new && $new->family_code !== $currentFamily) {
            $validator->errors()->add(
                'packaging_id',
                __('You can only change to another size within the same packaging family.')
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
