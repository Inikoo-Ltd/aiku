<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Sentry;

trait WithWixOrderWebhook
{
    use WithWixWebhookVerification;

    public function wixOrderIdFromEvent(array $event): ?string
    {
        return Arr::get($event, 'data.entityId')
            ?: Arr::get($event, 'data.actionEvent.body.order.id');
    }

    public function resolveWixUserFromEvent(array $event): ?WixUser
    {
        $instanceId = Arr::get($event, 'instanceId');

        if (blank($instanceId)) {
            return null;
        }

        $wixUsers = WixUser::where('wix_instance_id', $instanceId)
            ->whereNotNull('customer_id')
            ->whereNotNull('customer_sales_channel_id')
            ->orderBy('id')
            ->limit(2)
            ->get();

        // Which account should get the site's orders is for CS to decide, so it is reported, not guessed (PRE-43).
        if ($wixUsers->count() > 1) {
            Sentry::captureMessage("Wix instance $instanceId is linked to more than one channel, its orders go to wix_user {$wixUsers->first()->id}");
        }

        return $wixUsers->first();
    }
}
