<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:12:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Models\SysAdmin\User;
use App\Models\SysAdmin\UserPushSubscription;
use Lorisleiva\Actions\Concerns\AsAction;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendUserPushNotification
{
    use AsAction;

    public string $jobQueue = 'default';

    /**
     * @param array{title: string, body: string, url: string, tag?: string} $payload
     */
    public function handle(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');
        if ($subscriptions->isEmpty() || !config('services.webpush.public_key') || !config('services.webpush.private_key')) {
            return 0;
        }

        $webPush = app()->make(WebPush::class, ['auth' => ['VAPID' => [
            'subject'    => config('services.webpush.subject'),
            'publicKey'  => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]]);

        $encodedPayload = json_encode($payload);
        $subscriptions->each(fn (UserPushSubscription $subscription) => $webPush->queueNotification(
            Subscription::create([
                'endpoint'        => $subscription->endpoint,
                'keys'            => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
                'contentEncoding' => 'aes128gcm',
            ]),
            $encodedPayload
        ));

        $delivered = 0;
        foreach ($webPush->flush() as $report) {
            $subscription = $subscriptions->get($report->getEndpoint());
            if (!$subscription) {
                continue;
            }

            if ($report->isSuccess()) {
                $subscription->update(['last_used_at' => now()]);
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                $subscription->delete();
            }
        }

        return $delivered;
    }
}
