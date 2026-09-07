<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Context;

class SetAuditUserContext
{
    public function handle(Authenticated $event): void
    {
        Context::addHidden('audit_user', [get_class($event->user), $event->user->getAuthIdentifier()]);
    }
}
