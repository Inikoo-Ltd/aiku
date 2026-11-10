<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\ShoppingAssistant;

use App\Enums\EnumHelperTrait;

enum ShoppingAssistantEntryPointEnum: string
{
    use EnumHelperTrait;

    case PROFILE = 'profile';
    case SEARCH = 'search';
    case CHAT = 'chat';
    case COMMAND = 'command';
}
