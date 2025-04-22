<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Oct 2024 00:46:50 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Rules;

trait WithNoStrictRules
{
    protected function noStrictStoreRules($rules): array
    {
        $rules['created_at'] = ['sometimes', 'date'];
        $rules['fetched_at'] = ['sometimes', 'date'];
        $rules['deleted_at'] = ['sometimes', 'nullable', 'date'];
        $rules['source_id']  = ['sometimes', 'string', 'max:255'];

        return $rules;
    }

    protected function noStrictUpdateRules($rules): array
    {
        $rules['created_at']      = ['sometimes', 'date'];
        $rules['last_fetched_at'] = ['sometimes', 'date'];
        $rules['deleted_at']      = ['sometimes', 'nullable', 'date'];
        $rules['source_id']       = ['sometimes', 'string', 'max:255'];

        return $rules;
    }

    protected function orderNoStrictFields(array $rules): array
    {
        $rules['billing_locked']         = ['sometimes', 'boolean'];
        $rules['delivery_locked']        = ['sometimes', 'boolean'];
        $rules['submitted_at']           = ['sometimes', 'nullable', 'date'];
        $rules['in_warehouse_at']        = ['sometimes', 'nullable', 'date'];
        $rules['packed_at']              = ['sometimes', 'nullable', 'date'];
        $rules['finalised_at']           = ['sometimes', 'nullable', 'date'];
        $rules['dispatched_at']          = ['sometimes', 'nullable', 'date'];
        $rules['payment_amount']         = ['sometimes', 'numeric'];
        $rules['data']                   = ['sometimes', 'array'];
        $rules['reference']              = ['sometimes', 'string', 'max:64'];
        $rules['date']                   = ['sometimes', 'required', 'date'];
        $rules['cancelled_at']           = ['sometimes', 'nullable', 'date'];
        $rules['platform_id']            = ['sometimes', 'integer'];
        $rules['updated_by_customer_at'] = ['sometimes', 'nullable', 'date'];


        return $this->orderingAmountNoStrictFields($rules);
    }


    protected function orderingAmountNoStrictFields(array $rules): array
    {
        $rules['grp_exchange'] = ['sometimes', 'numeric'];
        $rules['org_exchange'] = ['sometimes', 'numeric'];

        $rules['gross_amount']    = ['sometimes', 'numeric'];
        $rules['goods_amount']    = ['sometimes', 'numeric'];
        $rules['services_amount'] = ['sometimes', 'numeric'];

        $rules['shipping_amount']  = ['sometimes', 'numeric'];
        $rules['charges_amount']   = ['sometimes', 'numeric'];
        $rules['insurance_amount'] = ['sometimes', 'numeric'];

        $rules['net_amount']   = ['sometimes', 'numeric'];
        $rules['tax_amount']   = ['sometimes', 'numeric'];
        $rules['total_amount'] = ['sometimes', 'numeric'];

        return $rules;
    }

}
