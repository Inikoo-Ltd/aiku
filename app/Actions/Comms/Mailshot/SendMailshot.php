<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Oct 2023 16:11:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Traits\WithMailshotStateOps;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Comms\Outbox;
use App\Actions\OrgAction;

class SendMailshot extends OrgAction
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

        //  TODO: make sure ProcessSendMailshot is implemented
        // ProcessSendMailshot::dispatch($mailshot);

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
