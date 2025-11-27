<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Nov 2025 12:48:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatAssignmentStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case ACTIVE = 'active';
    case RESOLVED = 'resolved';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            'pending'  => __('Pending'),
            'active'   => __('Active'),
            'resolved' => __('Resolved'),
            'rejected' => __('Rejected')
        ];
    }

}
