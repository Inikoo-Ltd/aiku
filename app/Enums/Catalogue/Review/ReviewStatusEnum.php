<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:54:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            self::PENDING->value  => __('Pending'),
            self::APPROVED->value => __('Approved'),
            self::REJECTED->value => __('Rejected'),
        ];
    }
}
