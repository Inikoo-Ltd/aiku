<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Oct 2025 10:57:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Oct 2025 10:58:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Description: Extracted the canLogWebUserRequest method into a reusable trait.
 */

namespace App\Actions\Traits;

use App\Models\CRM\WebUser;
use Illuminate\Routing\Route;

trait CanLogWebUserRequest
{
    public function canLogWebUserRequest(): bool
    {
        if (! config('app.log_user_requests')) {
            return false;
        }

        /* @var WebUser|null $webUser */
        $webUser = request()->user();

        // If there is an authenticated user from another guard that's not a WebUser, skip logging
        if ($webUser !== null && ! ($webUser instanceof WebUser)) {
            return false;
        }

        $routeName = request()->route()->getName();

        if (! str_starts_with($routeName, 'retina.') && ! str_starts_with($routeName, 'iris.')) {
            return false;
        }

        $skipPrefixes = ['retina.models', 'iris.models', 'retina.webhooks', 'iris.json', 'retina.json'];
        if ($routeName == 'retina.logout') {
            return false;
        }

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return false;
            }
        }

        if (request()->route() instanceof Route && request()->route()->getAction('uses') instanceof \Closure) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
