<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 12:22:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Audit;

use App\Enums\EnumHelperTrait;

enum AuditEventEnum: string
{
    use EnumHelperTrait;

    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case CUSTOMER_NOTE = 'customer_note';
    case MIGRATED = 'migrated';
    case OTHER = 'other';
    case PUBLISHED = 'published';

}
