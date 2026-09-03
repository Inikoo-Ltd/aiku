<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Webhooks;

use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;

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

        return WixUser::where('wix_instance_id', $instanceId)
            ->whereNotNull('customer_id')
            ->whereNotNull('customer_sales_channel_id')
            ->first();
    }
}
