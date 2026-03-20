<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 10 Mar 2026 15:27:51 UTC+08:00
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;

//NOTE: This function can be using by mailshot and newsletter
class AddRecipientsToMailshot extends OrgAction
{
    public function handle(Mailshot $mailshot, $recipients, $emailDeliveryChannel, Outbox $outbox): void
    {

    }
}
