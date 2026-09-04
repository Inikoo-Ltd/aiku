<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketTypeEnum: string
{
    use EnumHelperTrait;

    case HELP     = 'help';
    case CUSTOMER = 'customer';

    public static function labels(): array
    {
        return [
            'help'     => __('Help desk'),
            'customer' => __('Customer support'),
        ];
    }

    public function prefix(): string
    {
        return match ($this) {
            self::HELP     => 'HELP',
            self::CUSTOMER => 'AD',
        };
    }

    // ponytail: one counter per type for the whole install, add group_id to the sequence name if a second group ever needs its own numbering
    public function sequence(): string
    {
        return 'ticket_'.$this->value.'_number_seq';
    }
}
