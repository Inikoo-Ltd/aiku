<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 27 Feb 2025 15:28:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Comms\Traits\WithMailshotStateOps;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class SendProspectMailShot extends OrgAction
{
    use AsCommand;
    use AsAction;
    use WithMailshotStateOps;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        if ($mailshot->is_second_wave) {
            throw new \Exception('Action not available for second wave mailshot');
        }

        if (!$mailshot->start_sending_at) {
            data_set($modelData, 'start_sending_at', now());
        }

        //  NOTE: only allow sending if mailshot is in READY state
        if ($mailshot->state != MailshotStateEnum::READY) {
            return $mailshot;
        }

        data_set($modelData, 'state', MailshotStateEnum::SENDING);

        $mailshot->update($modelData);

        // NOTE: dispatch process
        PrepareProspectMailshotRecipients::dispatch($mailshot);


        return $mailshot;
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($mailshot, $this->validatedData);
    }
}
