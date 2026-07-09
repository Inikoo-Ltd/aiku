<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: 07/07/2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailAddress;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessGetSesClient
{
    use AsAction;

    private const array OUTBOX_BULK_GROUP = [
        OutboxCodeEnum::BASKET_LOW_STOCK,
        OutboxCodeEnum::REORDER_REMINDER,
        OutboxCodeEnum::REORDER_REMINDER_2ND,
        OutboxCodeEnum::REORDER_REMINDER_3RD,
        OutboxCodeEnum::OOS_NOTIFICATION,
        OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION,
        OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION,
        OutboxCodeEnum::REVIEW_REMINDER
    ];

    /**
     * @return array<int, array{key: string, secret: string, region: string, level: string}>
     */
    public function handle(?int $outboxId = null): array
    {
        $default = [
            'key'    => config('services.ses.key'),
            'secret' => config('services.ses.secret'),
            'region' => config('services.ses.region'),
            'level'  => 'default',
        ];

        $outbox = $outboxId ? Outbox::find($outboxId) : null;

        if (!$outbox) {
            return [$default];
        }

        // Mailshot: shop → shop failover → organisation failover → group
        if ($outbox->model_type === class_basename(Mailshot::class)) {
            $candidates = [
                'shop'                  => [$outbox->shop?->settings, 'email.provider'],
                'shop.failover'         => [$outbox->shop?->settings, 'email.provider.failover'],
                'organisation.failover' => [$outbox->organisation?->settings, 'email.provider.failover'],
                'group'                 => [$outbox->group?->settings, 'email.provider'],
            ];
        } elseif (in_array($outbox->code, self::OUTBOX_BULK_GROUP, true)) {
            // Bulk: organisation failover → group
            $candidates = [
                'organisation.failover' => [$outbox->organisation?->settings, 'email.provider.failover'],
                'group'                 => [$outbox->group?->settings, 'email.provider'],
            ];
        } elseif ($outbox->type === OutboxTypeEnum::USER_NOTIFICATION) {
            // Internal comms: organisation failover → group
            $candidates = [
                // email.provider.user_notification.access_id
                'group'                 => [$outbox->group?->settings, 'email.provider'],
                'group.failover'         => [$outbox->group?->settings, 'email.provider.failover'],
            ];
        } else {
            // Add the internal group new setting if is internal comms

            // Everything else: group only
            $candidates = [
                'group' => [$outbox->group?->settings, 'email.provider'],
            ];
        }

        $result = [];
        foreach ($candidates as $level => [$settings, $path]) {
            $credentials = $this->fromSettings($settings, $path);
            if ($credentials) {
                $result[] = [...$credentials, 'level' => $level];
            }
        }
        $result[] = $default;

        return $result;
    }

    /**
     * @return array{key: string, secret: string, region: string}|null
     */
    private function fromSettings(?array $settings, string $path = 'email.provider'): ?array
    {
        $key    = Arr::get($settings, "$path.access_id");
        $secret = Arr::get($settings, "$path.access_key");
        $region = Arr::get($settings, "$path.region");

        if (!$key || !$secret || !$region) {
            return null;
        }

        return [
            'key'    => $key,
            'secret' => $secret,
            'region' => $region,
        ];
    }
}
