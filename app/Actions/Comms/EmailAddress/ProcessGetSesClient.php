<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessGetSesClient
{
    use AsAction;

    private const SHOP_LEVEL_CODES = [
        OutboxCodeEnum::INVITE,
        OutboxCodeEnum::MARKETING,
        OutboxCodeEnum::NEWSLETTER,
    ];

    /**
     * @return array{key: string|null, secret: string|null, region: string|null}
     */
    public function handle(?int $outboxId = null): array
    {
        $outbox = $outboxId ? Outbox::find($outboxId) : null;

        if ($outbox && in_array($outbox->code, self::SHOP_LEVEL_CODES)) {
            $settings = $this->fromSettings($outbox->shop?->settings)
                ?? $this->fromSettings($outbox->organisation?->settings)
                ?? $this->fromSettings($outbox->group?->settings);
        } elseif ($outbox) {
            $settings = $this->fromSettings($outbox->organisation?->settings)
                ?? $this->fromSettings($outbox->group?->settings);
        } else {
            $settings = null;
        }

        return $settings ?? [
            'key'    => config('services.ses.key'),
            'secret' => config('services.ses.secret'),
            'region' => config('services.ses.region'),
        ];
    }

    /**
     * @return array{key: string|null, secret: string|null, region: string|null}|null
     */
    private function fromSettings(?array $settings): ?array
    {
        $accessId = Arr::get($settings, 'email.provider.access_id');

        if (!$accessId) {
            return null;
        }

        return [
            'key'    => $accessId,
            'secret' => Arr::get($settings, 'email.provider.access_key'),
            'region' => Arr::get($settings, 'email.provider.region'),
        ];
    }
}
