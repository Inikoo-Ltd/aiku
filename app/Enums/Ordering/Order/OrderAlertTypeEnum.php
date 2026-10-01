<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderAlertTypeEnum: string
{
    use EnumHelperTrait;

    case ECOM_SMALL = 'ecom_small';
    case ECOM_NORMAL = 'ecom_normal';
    case ECOM_BIG = 'ecom_big';
    case DROPSHIPPING_UNPAID = 'dropshipping_unpaid';
    case DROPSHIPPING_FIRST_CHANNEL_ORDER = 'dropshipping_first_channel_order';

    public const array SOUNDS = ['till', 'yeehaw', 'bell', 'coins', 'funny', 'oh_yeah'];

    public function label(): string
    {
        return match ($this) {
            self::ECOM_SMALL => __('Small order'),
            self::ECOM_NORMAL => __('Normal order'),
            self::ECOM_BIG => __('Big order'),
            self::DROPSHIPPING_UNPAID => __('Dropshipping order left unpaid'),
            self::DROPSHIPPING_FIRST_CHANNEL_ORDER => __('First order from a dropshipping channel'),
        };
    }

    public function defaultSound(): string
    {
        return $this === self::DROPSHIPPING_UNPAID ? 'bell' : 'till';
    }
}
