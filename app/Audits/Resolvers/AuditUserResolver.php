<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Audits\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Context;
use OwenIt\Auditing\Contracts\UserResolver;
use OwenIt\Auditing\Resolvers\UserResolver as BaseUserResolver;

class AuditUserResolver implements UserResolver
{
    public static function resolve(): ?Authenticatable
    {
        $user = BaseUserResolver::resolve();
        if ($user) {
            return $user;
        }

        $auditUser = Context::getHidden('audit_user');
        if (is_array($auditUser) && count($auditUser) === 2 && class_exists($auditUser[0])) {
            [$class, $id] = $auditUser;
            $model = new $class();

            return $model->forceFill([$model->getKeyName() => $id]);
        }

        return null;
    }
}
