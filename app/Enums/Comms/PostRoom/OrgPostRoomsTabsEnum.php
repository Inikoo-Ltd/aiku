<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 11-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Enums\Comms\PostRoom;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgPostRoomsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case OUTBOXES            = 'outboxes';
    case MAILSHOTS           = 'mailshots';

    public function blueprint(): array
    {
        return match ($this) {
            OrgPostRoomsTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            OrgPostRoomsTabsEnum::OUTBOXES => [
                'title' => __('Outboxes'),
                'icon'  => 'fal fa-inbox-out',
            ],
            OrgPostRoomsTabsEnum::MAILSHOTS => [
                'title' => __('Mailshots'),
                'icon'  => 'fal fa-folder',
            ],
        };
    }
}
