<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SupplyChain\SupplierPortalContact\UI\ShowSupplierPortalLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The supplier pages speak the language the supplier chose, so statuses and messages sent from the server match
 * the page. The worker serves other requests next, so the previous language is put back afterwards.
 */
class SetSupplierPortalLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        app()->setLocale(ShowSupplierPortalLogin::language($request));

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
