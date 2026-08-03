<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:54:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewStateEnum: string
{
    use EnumHelperTrait;

    case PRIVATE = 'private';
    case WAITING_APPROVAL = 'waiting_approval';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case REMOVED = 'removed';

    public static function labels(): array
    {
        return [
            self::PRIVATE->value  => __('Private'),
            self::WAITING_APPROVAL->value => __('Waiting approval'),
            self::REJECTED->value => __('Rejected'),
            self::PUBLISHED->value => __('Published'),
            self::REMOVED->value => __('Removed'),
        ];
    }
}
