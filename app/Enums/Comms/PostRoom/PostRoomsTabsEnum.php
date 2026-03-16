<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\PostRoom;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PostRoomsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case OUTBOXES = 'outboxes';
    case MAILSHOTS = 'mailshots';

    public function blueprint(): array
    {
        return match ($this) {
            PostRoomsTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            PostRoomsTabsEnum::OUTBOXES => [
                'title' => __('Outboxes'),
                'icon'  => 'fal fa-inbox-out',
            ],
            PostRoomsTabsEnum::MAILSHOTS => [
                'title' => __('Mailshots'),
                'icon'  => 'fal fa-folder',
            ],
        };
    }
}
