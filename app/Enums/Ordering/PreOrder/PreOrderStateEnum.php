<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\PreOrder;

use App\Enums\EnumHelperTrait;

enum PreOrderStateEnum: string
{
    use EnumHelperTrait;

    case WAITING_FOR_GOODS = 'waiting_for_goods';
    case AWAITING_PALLET_QUOTE = 'awaiting_pallet_quote';
    case BALANCE_REQUESTED = 'balance_requested';
    case RELEASED = 'released';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::WAITING_FOR_GOODS => __('Waiting for goods'),
            self::AWAITING_PALLET_QUOTE => __('Goods arrived, pallet quote needed'),
            self::BALANCE_REQUESTED => __('Balance requested'),
            self::RELEASED => __('Sent to warehouse'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::WAITING_FOR_GOODS, self::AWAITING_PALLET_QUOTE, self::BALANCE_REQUESTED];
    }

    /**
     * Its goods are here and kept for it, off the website, until it is released or cancelled.
     *
     * @return array<int, self>
     */
    public static function holdingStock(): array
    {
        return [self::AWAITING_PALLET_QUOTE, self::BALANCE_REQUESTED];
    }
}
