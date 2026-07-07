<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessGetSesClient
{
    use AsAction;

    private const OUTBOX_BULK_GROUP = [
        OutboxCodeEnum::BASKET_LOW_STOCK,
        OutboxCodeEnum::REORDER_REMINDER,
        OutboxCodeEnum::REORDER_REMINDER_2ND,
        OutboxCodeEnum::REORDER_REMINDER_3RD,
        OutboxCodeEnum::OOS_NOTIFICATION,
        OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION,
        OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION,
        OutboxCodeEnum::REVIEW_REMINDER
    ];

    private const OUTBOX_IMPORTANT_GROUP = [
        OutboxCodeEnum::PASSWORD_REMINDER,
    ];

    /**
     * @return array{key: string|null, secret: string|null, region: string|null}
     */
    public function handle(?int $outboxId = null): array
    {
        $default = [
            'key'    => config('services.ses.key'),
            'secret' => config('services.ses.secret'),
            'region' => config('services.ses.region'),
        ];

        $outbox = $outboxId ? Outbox::find($outboxId) : null;

        if (!$outbox) {
            return $default;
        }

        // Mailshot: shop → organisation → group
        if ($outbox->model_type === class_basename(Mailshot::class)) {
            return $this->fromSettings($outbox->shop?->settings)
                ?? $this->fromSettings($outbox->organisation?->settings)
                ?? $this->fromSettings($outbox->group?->settings)
                ?? $default;
        }

        // Bulk: organisation → group
        if (in_array($outbox->code, self::OUTBOX_BULK_GROUP, true)) {
            return $this->fromSettings($outbox->organisation?->settings)
                ?? $this->fromSettings($outbox->group?->settings)
                ?? $default;
        }

        // Important: always use default credentials
        if (in_array($outbox->code, self::OUTBOX_IMPORTANT_GROUP, true)) {
            return $default;
        }

        // Everything else: group only
        return $this->fromSettings($outbox->group?->settings) ?? $default;
    }

    /**
     * @return array{key: string|null, secret: string|null, region: string|null}|null
     */
    private function fromSettings(?array $settings): ?array
    {
        $key    = Arr::get($settings, 'email.provider.access_id');
        $secret = Arr::get($settings, 'email.provider.access_key');
        $region = Arr::get($settings, 'email.provider.region');

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
