<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll\UI;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PollsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE          = 'showcase';
    case POLL_OPTIONS = 'poll_options';

    public function blueprint(): array
    {
        return match ($this) {
            PollsTabsEnum::SHOWCASE => [
                'title' => __('showcase'),
                'icon'  => 'fas fa-info-circle',
            ],
            PollsTabsEnum::POLL_OPTIONS => [
                'title' => __('Poll Options'),
                'icon'  => 'fal fa-list-ul',
            ],
        };
    }
}
