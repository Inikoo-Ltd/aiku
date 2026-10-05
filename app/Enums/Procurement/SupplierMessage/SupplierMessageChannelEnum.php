<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\SupplierMessage;

use App\Enums\EnumHelperTrait;

enum SupplierMessageChannelEnum: string
{
    use EnumHelperTrait;

    case EMAIL    = 'email';
    case WHATSAPP = 'whatsapp';
    case WECHAT   = 'wechat';
}
