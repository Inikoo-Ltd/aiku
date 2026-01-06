<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:52:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Traits\WithMailshotStateOps;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Comms\Outbox;

class SendNewsLetter extends OrgAction
{
    use AsCommand;
    use AsAction;
    use WithMailshotStateOps;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        if (!$mailshot->start_sending_at) {
            data_set($modelData, 'start_sending_at', now());
        }
        data_set($modelData, 'state', MailshotStateEnum::SENDING);

        $mailshot->update($modelData);

        ProcessSendNewsletter::dispatch($mailshot);

        return $mailshot;
    }

    public function rules()
    {
        // TODO: implement rules
        return [];
    }


    public function asController(Shop $shop, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($mailshot, $this->validatedData);
    }
}
