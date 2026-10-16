<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 17:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotAbTestWinningMetricEnum: string
{
    use EnumHelperTrait;

    case OPEN_RATE         = 'open_rate';
    case CLICK_RATE        = 'click_rate';
    case PLACED_ORDER_RATE = 'placed_order_rate';

    public function label(): string
    {
        return match ($this) {
            self::OPEN_RATE         => __('Open rate'),
            self::CLICK_RATE        => __('Click rate'),
            self::PLACED_ORDER_RATE => __('Placed order rate'),
        };
    }
}
