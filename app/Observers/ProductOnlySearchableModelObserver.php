<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 21:07:55 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Observers;

use Laravel\Scout\ModelObserver;

class ProductOnlySearchableModelObserver extends ModelObserver
{
    public function saved($model): void
    {
        $searchableFields = [
            'code',
            'name',
            'description',
            'description_extra',
            'state',
            'is_for_sale',
            'created_at'
        ];

        if (!$model->wasChanged($searchableFields)) {
            return;
        }
        parent::saved($model);
    }

}
