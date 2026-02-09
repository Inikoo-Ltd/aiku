<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 6 Feb 2026 14:10:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\MailshotSendChannel\MailshotSendChannelStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StopMailshot extends OrgAction
{
    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        if ($mailshot->state != MailshotStateEnum::SENDING) {
            return $mailshot;
        }

        data_set($modelData, 'stopped_at', now());
        data_set($modelData, 'state', MailshotStateEnum::STOPPED);

        $mailshot->update($modelData);

        DB::table('email_delivery_channels')
            ->where('model_type', class_basename(Mailshot::class))
            ->where('model_id', $mailshot->id)
            ->where('state', MailshotSendChannelStateEnum::READY)
            ->update(['state' => MailshotSendChannelStateEnum::STOPPED]);

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
