<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateDeliveryNotes;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreDeliveryNoteItem extends OrgAction
{
    use WithAttributes;
    use WithNoStrictRules;


    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNoteItem
    {
        data_set($modelData, 'group_id', $deliveryNote->group_id);
        data_set($modelData, 'organisation_id', $deliveryNote->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNote->shop_id);


        if ($this->strict) {
            /** @var OrgStock $orgStock */
            $orgStock = OrgStock::withTrashed()->find($modelData['org_stock_id']);
            data_set($modelData, 'org_stock_family_id', $orgStock->org_stock_family_id);

            if ($orgStock->stock_id) {
                data_set($modelData, 'stock_id', $orgStock->stock_id);
                data_set($modelData, 'stock_family_id', $orgStock->stock->stock_family_id);
            }
        }

        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->create($modelData);
        if ($this->strict) {
            StorePicking::make()->action($deliveryNoteItem, [
                'quantity_required' => $deliveryNoteItem->quantity_required
            ]);
        }

        if ($deliveryNoteItem->transaction_id and $deliveryNoteItem->transaction->asset) {
            AssetHydrateDeliveryNotes::dispatch($deliveryNoteItem->transaction->asset);
        }



        return $deliveryNoteItem;
    }

    public function rules(): array
    {
        $rules = [
            'org_stock_id'      => [
                'required',
                Rule::Exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id)
            ],
            'transaction_id'    =>
                [
                    'required',
                    Rule::Exists('transactions', 'id')->where('shop_id', $this->shop->id)
                ],
            'quantity_required' => ['required', 'numeric']
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);


            $rules['transaction_id']      = [
                'sometimes',
                'nullable',
                Rule::Exists('transactions', 'id')->where('shop_id', $this->shop->id)
            ];
            $rules['state']               = ['sometimes', 'nullable', Rule::enum(DeliveryNoteItemStateEnum::class)];
            $rules['quantity_required']   = ['sometimes', 'numeric'];
            $rules['quantity_picked']     = ['sometimes', 'numeric'];
            $rules['quantity_packed']     = ['sometimes', 'numeric'];
            $rules['quantity_dispatched'] = ['sometimes', 'numeric'];
            $rules['org_stock_id']        = [
                'sometimes',
                'nullable',
                Rule::Exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id)
            ];
            $rules['stock_id']            = ['sometimes', 'nullable', 'integer'];
            $rules['stock_family_id']     = ['sometimes', 'nullable', 'integer'];
            $rules['org_stock_family_id'] = ['sometimes', 'nullable', 'integer'];
        }

        return $rules;
    }

    public function action(DeliveryNote $deliveryNote, array $modelData, int $hydratorsDelay = 0, $strict = true): DeliveryNoteItem
    {
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);


        return $this->handle($deliveryNote, $this->validatedData);
    }
}
