<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\CustomerClaim;

use App\Enums\EnumHelperTrait;

enum CustomerClaimSourceEnum: string
{
    use EnumHelperTrait;

    case MERLIN = 'merlin';
    case ACCOUNT = 'account';
    case CHAT = 'chat';
    case EMAIL = 'email';
    case STAFF = 'staff';
}
