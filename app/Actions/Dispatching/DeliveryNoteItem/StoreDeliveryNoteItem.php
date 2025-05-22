<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateDeliveryNotesIntervals;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreDeliveryNoteItem extends OrgAction
{
    use WithAttributes;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;


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

        if ($deliveryNoteItem->transaction_id && $deliveryNoteItem->transaction->asset) {
            AssetHydrateDeliveryNotesIntervals::dispatch($deliveryNoteItem->transaction->asset)->delay($this->hydratorsDelay);
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
            $rules = $this->deliveryNoteItemNonStrictRules($rules);
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
