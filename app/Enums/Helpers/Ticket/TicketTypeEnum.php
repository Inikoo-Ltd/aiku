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
    case ENGINEER = 'engineer';
    case CUSTOMER = 'customer';

    public static function labels(): array
    {
        return [
            'help'     => __('Help desk'),
            'engineer' => __('Engineering'),
            'customer' => __('Customer support'),
        ];
    }

    public function icon(): array
    {
        return match ($this) {
            self::HELP     => ['icon' => 'fal fa-life-ring', 'tooltip' => self::labels()['help']],
            self::ENGINEER => ['icon' => 'fal fa-toolbox', 'tooltip' => self::labels()['engineer']],
            self::CUSTOMER => ['icon' => 'fal fa-user-headset', 'tooltip' => self::labels()['customer']],
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::HELP     => 'HELP',
            self::ENGINEER => 'INI',
            self::CUSTOMER => 'CUS',
        };
    }

    /**
     * Customer tickets were raised as AD before the prefix became CUS, so old references stay searchable.
     *
     * @return array<int, string>
     */
    public static function searchPrefixes(): array
    {
        return [...array_map(fn (self $type) => $type->prefix(), self::cases()), 'AD'];
    }

    public function numberPadding(): int
    {
        return match ($this) {
            self::HELP     => 0,
            self::ENGINEER => 3,
            self::CUSTOMER => 3,
        };
    }

    // ponytail: one counter per type for the whole install, add group_id to the sequence name if a second group ever needs its own numbering
    public function sequence(): string
    {
        return 'ticket_'.$this->value.'_number_seq';
    }
}
