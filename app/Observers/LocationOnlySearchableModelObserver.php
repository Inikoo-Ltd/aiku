<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 21:07:55 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Observers;

use Laravel\Scout\ModelObserver;

class LocationOnlySearchableModelObserver extends ModelObserver
{
    public function saved($model): void
    {
        $searchableFields = [
            'code',
            'status',
            'created_at',
            'warehouse_area_id',
            'warehouse_id'
        ];


        if (!$model->wasChanged($searchableFields)) {
            return;
        }
        parent::saved($model);
    }

}
