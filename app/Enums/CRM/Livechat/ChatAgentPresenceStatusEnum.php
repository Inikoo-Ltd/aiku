<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatAgentPresenceStatusEnum: string
{
    use EnumHelperTrait;

    case ONLINE = 'online';
    case AWAY = 'away';
    case OFFLINE = 'offline';

    public static function labels(): array
    {
        return [
            'online'  => __('Online'),
            'away'    => __('Away'),
            'offline' => __('Offline'),
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'label' => self::labels()[$case->value] ?? $case->value,
                'value' => $case->value,
            ])
            ->toArray();
    }
}
