<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Oct 2023 16:11:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class SendScheduledMailshots
{
    use AsAction;

    public string $commandSignature = 'mailshot:send-scheduled';

    public function handle(): void
    {
        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $mailshots = Mailshot::where('state', MailshotStateEnum::SCHEDULED)
            ->whereIn('shop_id', $aikuShops)
            ->whereNull('source_id')
            ->get();


        /** @var Mailshot $mailshot */
        foreach ($mailshots as $mailshot) {
            if ($mailshot->scheduled_at !== null && $mailshot->scheduled_at->lte(now())) {
                SendMailShot::dispatch($mailshot);
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
