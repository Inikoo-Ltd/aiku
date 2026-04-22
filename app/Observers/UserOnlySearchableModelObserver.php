<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 21:53:17 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Observers;

use Laravel\Scout\ModelObserver;

class UserOnlySearchableModelObserver extends ModelObserver
{
    public function saved($model): void
    {
        $searchableFields = [
            'username',
            'email',
            'contact_name',
            'status',
            'created_at'
        ];


        if (!$model->wasChanged($searchableFields)) {
            return;
        }
        parent::saved($model);
    }

}
