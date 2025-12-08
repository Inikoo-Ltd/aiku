<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Nov 2024 17:41:58 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\OrgPostRoom;

use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Enums\EnumHelperTrait;

enum OrgPostRoomTypeEnum: string
{
    use EnumHelperTrait;

    case NEWSLETTER = 'newsletter';
    case MARKETING = 'marketing';
    case MARKETING_NOTIFICATION = 'marketing_notification'; // halfway between marketing and transactional
    case CUSTOMER_NOTIFICATION = 'customer_notification'; // e.g. forgot email, welcome email, etc
    case COLD_EMAIL = 'cold_emails'; // send to prospects
    case USER_NOTIFICATION = 'user_notification'; // internal notifications
    case TEST = 'test';

    public function label(): string
    {
        return match ($this) {
            PostRoomCodeEnum::NEWSLETTER => 'Newsletters',
            PostRoomCodeEnum::MARKETING => 'Marketing',
            PostRoomCodeEnum::MARKETING_NOTIFICATION => 'Marketing notifications',
            PostRoomCodeEnum::CUSTOMER_NOTIFICATION => 'Customer notifications',
            PostRoomCodeEnum::COLD_EMAIL => 'Cold emails',
            PostRoomCodeEnum::USER_NOTIFICATION => 'User notifications',
            PostRoomCodeEnum::TEST => 'Tests',
        };
    }
}
