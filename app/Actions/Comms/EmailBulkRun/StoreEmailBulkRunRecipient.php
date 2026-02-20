<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Feb 2026 11:08:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailBulkRun;

use App\Actions\OrgAction;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailBulkRunRecipient;

class StoreEmailBulkRunRecipient extends OrgAction
{
    public function handle(EmailBulkRun $emailBulkRun, array $modelData): EmailBulkRunRecipient
    {
        $result =  $emailBulkRun->recipients()->create($modelData);
        return $result;
    }
}
