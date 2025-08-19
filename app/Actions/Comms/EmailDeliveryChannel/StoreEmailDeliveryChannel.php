<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailDeliveryChannel;

use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailDeliveryChannel;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreEmailDeliveryChannel
{
    use AsAction;

    public function handle(Mailshot|EmailBulkRun $model, array $modelData = []): EmailDeliveryChannel
    {
        data_set($modelData, 'number_emails', 0, overwrite: false);

        $model->refresh();
        if ($model instanceof Mailshot && $model->state == MailshotStateEnum::STOPPED) {
            $modelData = $this->tagForStopTheDelivery($modelData);
        } elseif ($model instanceof EmailBulkRun && $model->state == EmailBulkRunStateEnum::STOPPED) {
            $modelData = $this->tagForStopTheDelivery($modelData);
        }

        /** @var EmailDeliveryChannel $EmailDeliveryChannel */
        return $model->channels()->create($modelData);
    }

    private function tagForStopTheDelivery($modelData)
    {
        data_set($modelData, 'state', EmailDeliveryChannelStateEnum::STOPPED->value);

        return $modelData;
    }

}
