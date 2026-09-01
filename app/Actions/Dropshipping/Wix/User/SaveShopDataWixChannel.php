<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class SaveShopDataWixChannel
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WixUser $wixUser): WixUser
    {
        try {
            $instance = $wixUser->getAppInstance();

            if (Arr::get($instance, 'message')) {
                return $wixUser;
            }

            $data = $wixUser->data ?? [];

            data_set($data, 'instance_id', Arr::get($instance, 'instance.instanceId'));
            data_set($data, 'app_name', Arr::get($instance, 'instance.appName'));
            data_set($data, 'is_free', Arr::get($instance, 'instance.isFree'));
            data_set($data, 'permissions', Arr::get($instance, 'instance.permissions'));
            data_set($data, 'site_id', Arr::get($instance, 'site.siteId'));
            data_set($data, 'site_display_name', Arr::get($instance, 'site.siteDisplayName'));
            data_set($data, 'locale', Arr::get($instance, 'site.locale'));
            data_set($data, 'currency', Arr::get($instance, 'site.paymentCurrency'));
            data_set($data, 'url', Arr::get($instance, 'site.url'));
            data_set($data, 'owner_email', Arr::get($instance, 'site.ownerEmail'));

            $modelData = [
                'data'        => $data,
                'wix_site_id' => Arr::get($instance, 'site.siteId') ?: $wixUser->wix_site_id,
                'site_url'    => Arr::get($instance, 'site.url') ?: $wixUser->site_url,
            ];

            $name = Arr::get($instance, 'site.siteDisplayName');
            if ($name) {
                $modelData['name'] = $name;
            }

            $email = Arr::get($instance, 'site.ownerEmail');
            if ($email) {
                $modelData['email'] = $email;
            }

            UpdateWixUser::make()->handle($wixUser, $modelData, false);

            $wixUser->refresh();
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        return $wixUser;
    }
}
