<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 15:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * HELP-4896: how an agent came to hold a conversation, so the buttons that take one can be
 * counted and the customer's wait can be measured from the moment it was taken.
 */
enum ChatAssignmentClaimedViaEnum: string
{
    use EnumHelperTrait;

    case ASSIGN_BUTTON = 'assign_button';
    case TAKE_OVER_LOCK = 'take_over_lock';
    case TAKE_OVER_BAR = 'take_over_bar';
    case REPLY = 'reply';

    public static function labels(): array
    {
        return [
            'assign_button'  => __('Assign to me'),
            'take_over_lock' => __('Took over from the lock'),
            'take_over_bar'  => __('Took over from the bar'),
            'reply'          => __('Replied'),
        ];
    }
}
