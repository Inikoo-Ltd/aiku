<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\LogUserRequestMiddleware;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Routing\Route;

function logUserRequestMiddlewareRequest(string $routeName = 'grp.org.accounting.refunds.show'): Request
{
    $request = Request::create('/org/sk/accounting/refunds/eu1r', 'GET');
    $request->setRouteResolver(fn () => tap(
        new Route(['GET'], '/org/{organisation}/accounting/refunds/{refund}', []),
        fn (Route $route) => $route->name($routeName)->bind($request)
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

test('only requests a person makes count as staff activity, not the polls of an open tab', function (string $routeName, bool $countsAsActivity) {
    config()->set('app.log_user_requests', true);
    Cache::forget('staff-last-active:1');

    $middleware = new class () extends LogUserRequestMiddleware {
        protected function recordUserRequest(Request $request, User $user, string $ip, array $geoLocation): void
        {
        }
    };

    $middleware->handle(logUserRequestMiddlewareRequest($routeName), fn () => response('ok'));

    expect(Cache::has('staff-last-active:1'))->toBe($countsAsActivity);
})->with([
    ['grp.org.accounting.refunds.show', true],
    ['grp.chat.staff.coworkers.index', false],
    ['grp.chat.staff.conversations.index', false],
    ['grp.chat.presence.track', false],
    ['grp.chat.inbox_timings.store', false],
]);

test('a request from a tab with no recent mouse or keyboard input is not staff activity', function () {
    config()->set('app.log_user_requests', true);
    Cache::forget('staff-last-active:1');

    $middleware = new class () extends LogUserRequestMiddleware {
        protected function recordUserRequest(Request $request, User $user, string $ip, array $geoLocation): void
        {
        }
    };

    $request = logUserRequestMiddlewareRequest('grp.tickets.index');
    $request->headers->set('X-Background-Request', '1');

    $middleware->handle($request, fn () => response('ok'));

    expect(Cache::has('staff-last-active:1'))->toBeFalse();
});
