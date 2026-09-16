<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ConnectShopMailbox extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop, int $userId): RedirectResponse
    {
        $state = Crypt::encryptString(json_encode([
            'shop_id' => $shop->id,
            'user_id' => $userId,
            'return'  => route('grp.org.shops.show.settings.edit', [$shop->organisation->slug, $shop->slug]),
        ]));

        return Redirect::away(GmailClient::authorizationUrl($state, route('grp.gmail.callback')));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request->user()->id);
    }
}
