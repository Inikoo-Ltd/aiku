<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\SupplierWechatConversation;

use App\Enums\EnumHelperTrait;

enum SupplierWechatConversationKindEnum: string
{
    use EnumHelperTrait;

    case SINGLE = 'single';
    case GROUP  = 'group';
}
