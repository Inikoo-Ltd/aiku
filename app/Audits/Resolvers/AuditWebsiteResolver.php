<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jul 2024 00:45:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Audits\Resolvers;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class AuditWebsiteResolver implements Resolver
{
    public static function resolve(Auditable $auditable)
    {

        if ($auditable->website_id) {
            return $auditable->website_id;
        }

        if (class_basename($auditable) == 'Website') {
            return $auditable->id;
        }

        return null;
    }
}
