<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotTypeEnum: string
{
    use EnumHelperTrait;

    case NEWSLETTER = 'newsletter';
    case MARKETING = 'marketing';
    case INVITE = 'invite';
    case ABANDONED_CART = 'abandoned_cart';

}
