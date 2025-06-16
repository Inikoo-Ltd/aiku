<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Oct 2024 13:04:44 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers;

use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

trait WithSaveMigrationHistory
{
    protected function saveMigrationHistory($model, $data): void
    {
        $model->auditEvent = 'migration';
        $model->isCustomEvent = true;
        $model->auditCustomOld = [];
        $model->auditCustomNew = $data;
        Event::dispatch(new AuditCustom($model));
    }
}
