<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:11:51 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit\Actions\Search;

use App\Actions\Search\GetSearchAnalytics;
use App\Actions\Search\RecordSearchClick;
use App\Actions\Search\Search;
use App\Actions\Search\SearchSysAdmin;
use App\Actions\Search\StoreSearchLog;
use App\Models\Helpers\SearchLog;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
    ['grp.org.shops.show.ordering.orders.index', 'orders'],
    ['grp.org.shops.show.reviews.dashboard', 'reviews'],
    ['grp.org.accounting.invoices.index', 'accounting'],
    ['grp.org.accounting.payments.index', 'accounting'],
    ['grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index', 'inventory'],
    ['grp.org.warehouses.show.dispatching.delivery_notes.index', 'dispatching'],
    ['grp.org.warehouses.show.infrastructure.locations.index', 'locations'],
    ['grp.goods.stocks.index', 'goods'],
    ['grp.goods.trade-units.index', 'goods'],
    ['grp.supply-chain.suppliers.index', 'supply_chain'],
    ['grp.trade_units.units.index', 'trade_units'],
    ['grp.trade_units.brands.index', 'trade_units'],
    ['grp.masters.master_shops.show', 'master_shop'],
    ['grp.masters.master_shops.show.master_products.index', 'master_shop'],
    ['grp.org.shops.show.billables.charges.index', 'billables'],
    ['grp.org.shops.show.discounts.offers.index', 'offers'],
    ['grp.org.shops.show.marketing.mailshots.index', 'marketing'],
    ['grp.org.shops.show.web.webpages.index', 'website'],
    ['grp.org.shops.show.dashboard.show', 'shop_accounting'],
    ['grp.org.hr.employees.index', 'hr'],
    ['grp.chat.dashboard', 'chat'],
    ['grp.org.chat.dashboard', 'chat'],
]);

it('returns empty array for unknown search scope', function () {
    $action = app(Search::class);

    expect($action->handle('unknown-scope', 'john'))->toBe([]);
});

it('caches results for queries of two characters or fewer', function () {
    $expected = ['scope' => 'sysadmin', 'results' => ['users' => [], 'guests' => []]];
    SearchSysAdmin::mock()->shouldReceive('handle')->once()->andReturn($expected);

    $action = app(Search::class);

    expect($action->handle('sysadmin', 'ab'))->toBe($expected)
        ->and($action->handle('sysadmin', 'ab'))->toBe($expected);
});

it('logs searches, records clicks and aggregates analytics', function () {
    Http::fake(['*/analytics/events' => Http::response(['ok' => true])]);
    SearchLog::query()->delete();
    $group = Group::first();

    $user = \App\Models\SysAdmin\User::first();

    $clickedUlid = (string)Str::ulid();
    StoreSearchLog::run([
        'ulid'          => $clickedUlid,
        'group_id'      => $group->id,
        'user_id'       => $user?->id,
        'scope'         => 'catalogue',
        'query'         => 'bath bomb',
        'results_count' => 12,
    ]);
    StoreSearchLog::run([
        'ulid'          => (string)Str::ulid(),
        'group_id'      => $group->id,
        'scope'         => 'catalogue',
        'query'         => 'nonexistent thing',
        'results_count' => 0,
    ]);

    RecordSearchClick::run($clickedUlid, 'https://app.aiku.test/majordomo/redirect-product/1');

    expect(SearchLog::where('ulid', $clickedUlid)->value('clicked_at'))->not->toBeNull();

    $analytics = GetSearchAnalytics::run($group);

    expect($analytics['total_searches'])->toBe(2)
        ->and($analytics['click_through'])->toEqual(50)
        ->and($analytics['zero_results_rate'])->toEqual(50)
        ->and($analytics['top_queries'][0]['query'])->toBe('bath bomb')
        ->and((int)$analytics['top_queries'][0]['clicks'])->toBe(1)
        ->and($analytics['top_zero_queries'][0]['query'])->toBe('nonexistent thing');

    if ($user) {
        expect($analytics['top_searchers'][0]['username'])->toBe($user->username)
            ->and((int)$analytics['top_searchers'][0]['searches'])->toBe(1)
            ->and((int)$analytics['top_searchers'][0]['clicks'])->toBe(1);
    }
});

it('collapses type-ahead query refinements into a single search log', function () {
    SearchLog::query()->delete();
    $group = Group::first();

    $base = [
        'group_id'   => $group->id,
        'scope'      => 'catalogue',
        'session_id' => 'refine-session',
    ];

    StoreSearchLog::run([...$base, 'ulid' => (string)Str::ulid(), 'query' => 'e', 'results_count' => 100]);
    StoreSearchLog::run([...$base, 'ulid' => (string)Str::ulid(), 'query' => 'eo', 'results_count' => 20]);
    $finalUlid = (string)Str::ulid();
    StoreSearchLog::run([...$base, 'ulid' => $finalUlid, 'query' => 'eo-01', 'results_count' => 3]);

    expect(SearchLog::count())->toBe(1)
        ->and(SearchLog::first()->query)->toBe('eo-01')
        ->and(SearchLog::first()->ulid)->toBe($finalUlid)
        ->and(SearchLog::first()->results_count)->toBe(3);

    StoreSearchLog::run([...$base, 'ulid' => (string)Str::ulid(), 'query' => 'bath bomb', 'results_count' => 5]);

    expect(SearchLog::count())->toBe(2);

    StoreSearchLog::run(['group_id' => $group->id, 'scope' => 'catalogue', 'session_id' => 'other-session', 'ulid' => (string)Str::ulid(), 'query' => 'bath bombs', 'results_count' => 5]);

    expect(SearchLog::count())->toBe(3);

    SearchLog::query()->delete();
});

it('does not cache results for queries longer than two characters', function () {
    $expected = ['scope' => 'sysadmin', 'results' => ['users' => [], 'guests' => []]];
    SearchSysAdmin::mock()->shouldReceive('handle')->twice()->andReturn($expected);

    $action = app(Search::class);

    $action->handle('sysadmin', 'abc');
    $action->handle('sysadmin', 'abc');
});
