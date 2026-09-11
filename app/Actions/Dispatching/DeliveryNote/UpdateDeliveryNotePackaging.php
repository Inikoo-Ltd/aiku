<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\UpdateOrderPackaging;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($deliveryNote, $modelData) {
            $deliveryNote = $this->update($deliveryNote, $modelData);

            $this->repriceOrderPackaging($deliveryNote);

            return $deliveryNote;
        });
    }

    private function repriceOrderPackaging(DeliveryNote $deliveryNote): void
    {
        $order = $deliveryNote->orders()->first();

        if (!$order || !$deliveryNote->packaging_id || $order->packaging_id == $deliveryNote->packaging_id) {
            return;
        }

        if (!Arr::get($order->data ?? [], 'ordered_packaging_id') && $order->packaging_id) {
            $order->update([
                'data' => array_merge($order->data ?? [], ['ordered_packaging_id' => $order->packaging_id]),
            ]);
        }

        UpdateOrderPackaging::make()->action($order, [
            'packaging_id' => $deliveryNote->packaging_id,
            'leaflet_ids'  => $order->insert_types ?? [],
        ]);

        CalculateOrderTotalAmounts::run($order->refresh());
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

        $allowedIds = array_column(
            $this->getPackagingOptions($this->deliveryNote, $this->effectivePackaging($this->deliveryNote)?->family_code),
            'id'
        );

        if (!in_array((int) $packagingId, $allowedIds, true)) {
            $validator->errors()->add(
                'packaging_id',
                __('This packaging is not one of the options for this delivery note.')
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
