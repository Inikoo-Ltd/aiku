<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DisconnectShopMailbox extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop): RedirectResponse
    {
        $settings = $shop->settings ?? [];
        Arr::forget($settings, 'gmail');

        $this->update($shop, ['settings' => $settings]);

        Cache::forget("gmail-access-token:{$shop->id}");

        return Redirect::route('grp.org.shops.show.settings.edit', [$shop->organisation->slug, $shop->slug])
            ->with('notification', [
                'status'      => 'success',
                'title'       => __('Gmail disconnected'),
                'description' => __('This shop\'s mailbox is no longer connected.'),
            ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
