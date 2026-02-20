<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Nov 2023 13:55:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\MailshotSendChannel\MailshotSendChannelStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailDeliveryChannel;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ResumeMailshot extends OrgAction
{
    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        if ($mailshot->state != MailshotStateEnum::STOPPED) {
            return $mailshot;
        }


        data_set($modelData, 'stopped_at', null);
        data_set($modelData, 'state', MailshotStateEnum::SENDING);


        $mailshot->update($modelData);

        DB::table('email_delivery_channels')
            ->where('model_type', class_basename(Mailshot::class))
            ->where('model_id', $mailshot->id)
            ->where('state', MailshotSendChannelStateEnum::STOPPED)
            ->update(['state' => MailshotSendChannelStateEnum::READY]);


        EmailDeliveryChannel::where('model_type', class_basename(Mailshot::class))
            ->where('model_id', $mailshot->id)
            ->where('state', MailshotSendChannelStateEnum::READY)
            ->chunk(1000, function ($channels) {
                foreach ($channels as $mailshotSendChannel) {
                    SendEmailDeliveryChannel::dispatch($mailshotSendChannel);
                }
            });

        return $mailshot;
    }

    public function htmlResponse(Mailshot $mailshot): RedirectResponse
    {
        return redirect()->route(
            match ($mailshot->type) {
                MailshotTypeEnum::NEWSLETTER => 'grp.org.shops.show.marketing.newsletters.show',
                MailshotTypeEnum::MARKETING => 'grp.org.shops.show.marketing.mailshots.show',
            },
            [
                'organisation' => $this->organisation->slug,
                'shop' => $this->shop->slug,
                'mailshot' => $mailshot->slug
            ]
        );
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
