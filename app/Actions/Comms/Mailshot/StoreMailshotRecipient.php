<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 16:52:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;

class StoreMailshotRecipient extends OrgAction
{
    public function handle(Mailshot $mailshot, array $modelData): MailshotRecipient
    {
        $result =  $mailshot->recipients()->create($modelData);
        return $result;
    }
}
