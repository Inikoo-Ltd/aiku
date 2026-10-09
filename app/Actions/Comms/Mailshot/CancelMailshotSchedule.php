<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 16:28:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Lorisleiva\Actions\ActionRequest;

class CancelMailshotSchedule extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;

    public function handle(Mailshot $mailshot): Mailshot
    {
        if ($mailshot->state !== MailshotStateEnum::SCHEDULED) {
            return $mailshot;
        }

        $this->update($mailshot, [
            'scheduled_at' => null,
            'state' => MailshotStateEnum::READY,
        ]);

        return $mailshot->refresh();
    }

    public function asController(Shop $shop, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot);
    }
}
