<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 25 Mar 2026 13:25:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class MailshotHasUnsubscribeLink
{
    use AsAction;

    public function handle(Mailshot $mailshot): bool
    {
        $emailHtmlBody = GetHtmlLayout::run($mailshot);

        if (preg_match('/\{\{unsubscribe}}|\[unsubscribe]/i', $emailHtmlBody)) {
            return true;
        }
        return false;
    }
}
