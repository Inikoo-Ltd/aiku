<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 21:11:50 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Observers;

use Laravel\Scout\ModelObserver;

class CustomerOnlySearchableModelObserver extends ModelObserver
{
    public function saved($model): void
    {
        $searchableFields = [
            'shop_id',
            'status',
            'state',
            'reference',
            'name',
            'contact_name',
            'company_name',
            'eori',
            'email',
            'phone',
            'contact_website',
            'identity_document_number',
            'internal_notes',
            'warehouse_internal_notes',
            'warehouse_public_notes',
            'created_at'
        ];

        if (!$model->wasChanged($searchableFields)) {
            return;
        }
        parent::saved($model);
    }


}
