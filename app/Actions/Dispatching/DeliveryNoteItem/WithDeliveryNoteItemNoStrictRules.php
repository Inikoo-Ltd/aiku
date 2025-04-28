<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 01:18:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Validation\Rule;

trait WithDeliveryNoteItemNoStrictRules
{
    public function deliveryNoteItemNonStrictRules(array $rules): array
    {
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
        $rules['weight']              = ['sometimes', 'numeric', 'min:0'];

        $rules['date']                = ['sometimes', 'date'];
        $rules['queued_at']           = ['sometimes', 'nullable', 'date'];
        $rules['handling_at']         = ['sometimes', 'nullable', 'date'];
        $rules['handling_blocked_at'] = ['sometimes', 'nullable', 'date'];
        $rules['packed_at']           = ['sometimes', 'nullable', 'date'];
        $rules['finalised_at']        = ['sometimes', 'nullable', 'date'];
        $rules['dispatched_at']       = ['sometimes', 'nullable', 'date'];
        $rules['cancelled_at']        = ['sometimes', 'nullable', 'date'];
        $rules['start_picking']       = ['sometimes', 'nullable', 'date'];
        $rules['end_picking']         = ['sometimes', 'nullable', 'date'];
        $rules['start_packing']       = ['sometimes', 'nullable', 'date'];
        $rules['end_packing']         = ['sometimes', 'nullable', 'date'];

        $rules['order_id']    = ['sometimes', 'nullable', 'integer'];
        $rules['customer_id'] = ['sometimes', 'nullable', 'integer'];
        $rules['invoice_id']  = ['sometimes', 'nullable', 'integer'];

        $rules['revenue_amount']     = ['sometimes', 'nullable', 'numeric'];
        $rules['org_revenue_amount'] = ['sometimes', 'nullable', 'numeric'];
        $rules['grp_revenue_amount'] = ['sometimes', 'nullable', 'numeric'];


        return $rules;
    }
}
