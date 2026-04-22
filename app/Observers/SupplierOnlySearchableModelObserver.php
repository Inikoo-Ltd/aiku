<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 21:54:38 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Observers;

use Laravel\Scout\ModelObserver;

class SupplierOnlySearchableModelObserver extends ModelObserver
{
    public function saved($model): void
    {
        $searchableFields = [
            'agent_id',
            'status',
            'code',
            'name',
            'contact_name',
            'company_name',
            'email',
            'phone',
            'contact_website',
            'identity_document_number',
            'created_at'
        ];

        if (!$model->wasChanged($searchableFields)) {
            return;
        }
        parent::saved($model);
    }

}
