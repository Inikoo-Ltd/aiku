<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:52:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Comms\Outbox;

class SendMailShot extends OrgAction
{
    public function handle(Mailshot $mailshot): Mailshot
    {
        $modelData = [];
        if ($mailshot->is_second_wave) {
            abort(400, 'Cannot send second wave mailshot');
        }


        if ($mailshot->state == MailshotStateEnum::SCHEDULED && $mailshot->scheduled_at !== null && $mailshot->scheduled_at->lte(now())) {
            $mailshot = UpdateMailshot::run($mailshot, [
                'state' => MailshotStateEnum::READY,
                'ready_at' => now(),
            ]);
        }


        if ($mailshot->state != MailshotStateEnum::READY) {
            return $mailshot;
        }

        if (!$mailshot->start_sending_at) {
            data_set($modelData, 'start_sending_at', now());
        }

        data_set($modelData, 'state', MailshotStateEnum::SENDING);

        $mailshot->update($modelData);

        if ($mailshot->type === MailshotTypeEnum::NEWSLETTER) {
            PrepareNewsletterRecipients::dispatch($mailshot);
        } elseif ($mailshot->type === MailshotTypeEnum::MARKETING) {
            PrepareMailshotRecipients::dispatch($mailshot);
        }

        return $mailshot;
    }


    public function asController(Shop $shop, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot);
    }
}
