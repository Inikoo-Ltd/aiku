<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:11:51 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit\Actions\Search;

use App\Actions\Search\Search;

it('returns null route scope for unknown route prefix', function () {
    $action = app(Search::class);

    expect($action->getRouteScope('unknown.route'))->toBeNull();
});

it('maps known route prefixes to expected scopes', function (string $route, string $expectedScope) {
    $action = app(Search::class);

    expect($action->getRouteScope($route))->toBe($expectedScope);
})->with([
    ['grp.sysadmin.users.index', 'sysadmin'],
    ['grp.org.shops.show.catalogue.products.index', 'catalogue'],
    ['grp.org.shops.show.crm.prospects.index', 'prospects'],
    ['grp.org.shops.show.crm.customers.index', 'customers'],
]);

it('returns empty array for unknown search scope', function () {
    $action = app(Search::class);

    expect($action->handle('unknown-scope', 'john'))->toBe([]);
});
