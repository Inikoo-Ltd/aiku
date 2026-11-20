<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum SlackFileStateEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case COPYING = 'copying';
    case COPIED = 'copied';
    case EXTERNAL = 'external';
    case SKIPPED = 'skipped';
    case NOT_AVAILABLE = 'not_available';
    case FAILED = 'failed';

    /**
     * States the backfill still has to deal with; failed ones only when asked, as their cause usually needs fixing first.
     *
     * @return list<string>
     */
    public static function toCopy(bool $withFailed = false): array
    {
        return array_merge([self::PENDING->value, self::COPYING->value], $withFailed ? [self::FAILED->value] : []);
    }
}
