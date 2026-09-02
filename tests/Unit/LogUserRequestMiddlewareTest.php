<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\LogUserRequestMiddleware;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

function logUserRequestMiddlewareRequest(): Request
{
    $request = Request::create('/org/sk/accounting/refunds/eu1r', 'GET');
    $request->setRouteResolver(fn () => tap(
        new Route(['GET'], '/org/{organisation}/accounting/refunds/{refund}', []),
        fn (Route $route) => $route->name('grp.org.accounting.refunds.show')->bind($request)
    ));
    $request->setUserResolver(fn () => new User(['id' => 1]));

    return $request;
}

test('request survives when queueing the user request log throws', function () {
    config()->set('app.log_user_requests', true);

    $middleware = new class () extends LogUserRequestMiddleware {
        protected function recordUserRequest(Request $request, User $user, string $ip, array $geoLocation): void
        {
            throw new RuntimeException('BUSY Redis is busy running a script.');
        }
    };

    $response = $middleware->handle(logUserRequestMiddlewareRequest(), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});
