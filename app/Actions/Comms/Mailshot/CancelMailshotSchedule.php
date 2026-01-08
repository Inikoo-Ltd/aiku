<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 16:28:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;

class CancelMailshotSchedule extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot): void
    {
        if ($mailshot->state !== MailshotStateEnum::SCHEDULED) {
            return;
        }

        $this->update($mailshot, [
            'scheduled_at' => null,
            'state' => MailshotStateEnum::READY,
        ]);
    }

    public function asController(Shop $shop, Outbox $outbox, Mailshot $mailshot): void
    {
        $this->handle($mailshot);
    }
}
