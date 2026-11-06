<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\Crawl\AuditWebsite;
use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Tags the Sentry events of a storefront request made by a site audit with the audit's id, so the
 * audit links each broken page to the errors it raised.
 */
class TagSentryWithSiteAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $auditId = (string) $request->header(AuditWebsite::AUDIT_HEADER);

        if (ctype_digit($auditId)) {
            configureScope(fn (Scope $scope) => $scope->setTag('site_audit', $auditId));
        }

        return $next($request);
    }
}
