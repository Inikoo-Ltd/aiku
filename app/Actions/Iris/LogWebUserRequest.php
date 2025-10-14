<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 11:43:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris;

use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Models\CRM\WebUser;
use Illuminate\Routing\Route;
use Lorisleiva\Actions\Concerns\AsAction;

class LogWebUserRequest
{
    use AsAction;


    public function handle(): void
    {
        if ($this->canLogWebUserRequest()) {
            ProcessRetinaWebUserRequest::dispatch(
                request()->user(),
                now(),
                [
                    'name'      => request()->route()->getName(),
                    'arguments' => request()->route()->originalParameters(),
                    'url'       => request()->path(),
                ],
                request()->ip(),
                request()->header('User-Agent')
            );
        }
    }


    public function canLogWebUserRequest(): bool
    {
        if (!config('app.log_user_requests')) {
            return false;
        }

        /* @var WebUser|null $webUser */
        $webUser = request()->user();

        // If there is an authenticated user from another guard that's not a WebUser, skip logging
        if ($webUser !== null && !($webUser instanceof WebUser)) {
            return false;
        }

        $routeName = request()->route()->getName();

        if (!str_starts_with($routeName, 'retina.') && !str_starts_with($routeName, 'iris.')) {
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
