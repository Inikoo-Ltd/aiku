<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Events\BroadcastPersonalNotification;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use App\Notifications\WebpageEditAccessNotification;
use Lorisleiva\Actions\Concerns\AsObject;

class NotifyWebpageEditAccess
{
    use AsObject;

    public function handle(Webpage $webpage, ?User $recipient, User $actor, string $title, string $body): void
    {
        if (!$recipient || $recipient->id == $actor->id) {
            return;
        }

        $url          = $this->webpageUrl($webpage);
        $notification = new WebpageEditAccessNotification($title, $body, $url);
        $recipient->notify($notification);

        BroadcastPersonalNotification::dispatch($recipient->id, [
            'id'    => $notification->id,
            'title' => $title,
            'body'  => $body,
            'route' => $url,
        ]);
    }

    public function webpageUrl(Webpage $webpage): string
    {
        if ($webpage->shop->type == ShopTypeEnum::FULFILMENT) {
            return route('grp.org.fulfilments.show.web.webpages.show', [
                'organisation' => $webpage->organisation->slug,
                'fulfilment'   => $webpage->shop->fulfilment->slug,
                'website'      => $webpage->website->slug,
                'webpage'      => $webpage->slug,
            ]);
        }

        return route('grp.org.shops.show.web.webpages.show', [
            'organisation' => $webpage->organisation->slug,
            'shop'         => $webpage->shop->slug,
            'website'      => $webpage->website->slug,
            'webpage'      => $webpage->slug,
        ]);
    }
}
