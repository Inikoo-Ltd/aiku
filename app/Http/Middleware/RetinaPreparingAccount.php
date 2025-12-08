<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Reviewed: Tue, 01 Apr 2025 22:40:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Web\Website\WebsiteTypeEnum;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RetinaPreparingAccount
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->get('website')->type === WebsiteTypeEnum::FULFILMENT->value
            && $request->user()
            && $request->user()?->customer?->fulfilmentCustomer?->rentalAgreement == null
        ) {
            return Inertia::render('Errors/ErrorInApp', [
                'error' => [
                    'code' => 403,
                    'title' => 'We still prepare your account',
                    'description' => 'please come back shortly.',
                ],
            ]);
        }

        return $next($request);
    }
}
