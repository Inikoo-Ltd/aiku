<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Oct 2024 23:42:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

trait WithNoStrictProcurementOrderRules
{
    protected function noStrictProcurementOrderRules($rules)
    {
        $rules['currency_id'] = ['sometimes', 'required', 'exists:currencies,id'];
        $rules['parent_code'] = ['sometimes', 'required', 'string', 'max:256'];
        $rules['parent_name'] = ['sometimes', 'required', 'string', 'max:256'];
        $rules['grp_exchange'] = ['sometimes', 'numeric'];
        $rules['org_exchange'] = ['sometimes', 'numeric'];

        return $rules;
    }

    protected function noStrictPurchaseOrderDatesRules($rules)
    {
        $rules['date'] = ['sometimes', 'date'];

        $rules['submitted_at'] = ['sometimes', 'nullable', 'date'];
        $rules['consolidated_at'] = ['sometimes', 'nullable', 'date'];
        $rules['cancelled_at'] = ['sometimes', 'nullable', 'date'];

        return $rules;
    }

    protected function noStrictStockDeliveryRules($rules)
    {
        $rules['date'] = ['sometimes', 'date'];

        $rules['dispatched_at'] = ['sometimes', 'nullable', 'date'];
        $rules['received_at'] = ['sometimes', 'nullable', 'date'];
        $rules['checked_at'] = ['sometimes', 'nullable', 'date'];
        $rules['placed_at'] = ['sometimes', 'nullable', 'date'];
        $rules['cancelled_at'] = ['sometimes', 'nullable', 'date'];

        return $rules;
    }
}
