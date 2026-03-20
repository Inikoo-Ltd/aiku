<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Oct 2023 16:11:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMailshotScheduled
{
    use AsAction;

    public string $commandSignature = 'mailshot:send-scheduled';

    public function handle(): void
    {
        $mailshots = Mailshot::where('state', MailshotStateEnum::SCHEDULED)
            ->whereNotNull('ready_at')
            ->get();


        /** @var Mailshot $mailshot */
        foreach ($mailshots as $mailshot) {
            if ($mailshot->ready_at->format('Y-m-d H:i') >= now()->format('Y-m-d H:i')) {
                SendMailShot::dispatch($mailshot);
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
