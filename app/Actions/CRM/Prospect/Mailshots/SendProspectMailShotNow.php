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
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Comms\Outbox;

class SendProspectMailShotNow extends OrgAction
{
    use AsCommand;
    use AsAction;
    use WithMailshotStateOps;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        // NOTE: For testing purposes, only available for Ukraine
        if ($mailshot->shop_id !== 44) {
            throw new \Exception('Action only available for Ukraine');
        }

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

        // NOTE: dispatch process based on mailshot type
        if ($mailshot->type === MailshotTypeEnum::MARKETING) {
            ProcessSendProspectMailshot::dispatch($mailshot);
        }

        return $mailshot;
    }

    public function rules()
    {
        // TODO: implement rules
        return [];
    }


    public function asController(Shop $shop, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        // outbox is not used in this action, but it's required by the route
        $this->initialisationFromShop($shop, $request);
        return $this->handle($mailshot, $this->validatedData);
    }
}
