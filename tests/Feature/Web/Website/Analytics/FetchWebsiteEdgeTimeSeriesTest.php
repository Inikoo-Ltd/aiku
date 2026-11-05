<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Actions\Web\Website\Cloudflare\FetchWebsiteEdgeTimeSeries;
use App\Actions\Web\Website\Cloudflare\MonitorEdgeRedirects;
use App\Actions\Web\Website\UI\GetWebsitesEdgeTraffic;
use App\Actions\Web\Website\UI\IndexWebsitesEdgeDatacenters;
use App\Actions\Web\Website\UI\IndexWebsitesEdgeTraffic;
use App\Actions\Web\Website\UI\IndexWebsiteVisitorCountries;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Middleware\CountOctaneStorefrontPage;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteEdgeCountryDay;
use App\Models\Web\WebsiteEdgeDatacenterDay;
use App\Models\Web\WebsiteEdgeTimeSeries;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->user->updateQuietly(['settings' => []]);
    $this->website = Website::factory()->create([
        'organisation_id'    => $this->organisation->id,
        'shop_id'            => $this->shop->id,
        'group_id'           => $this->organisation->group_id,
        'type'               => WebsiteTypeEnum::B2C,
        'state'              => WebsiteStateEnum::LIVE,
        'status'             => true,
        'cloudflare_zone_id' => $zone = Str::random(32),
        'cloudflare_token'   => encrypt('edge-test-token'),
    ]);
    $this->zone = $zone;
});

function edgeGroup(string $date, array $dimensions, int $count, ?float $originMs = null): array
{
    return array_filter([
        'count'      => $count,
        'avg'        => $originMs === null ? null : ['originResponseDurationMs' => $originMs],
        'dimensions' => ['date' => $date, ...$dimensions],
    ], fn ($value) => $value !== null);
}

it('stores what cloudflare did with storefront html per day and rolls the days up', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [
            edgeGroup('2026-10-29', ['cacheStatus' => 'dynamic'], 100, 200),
            edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 40, 100),
            edgeGroup('2026-10-30', ['cacheStatus' => 'stale'], 5, 0),
            edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 20, 300),
            edgeGroup('2026-10-30', ['cacheStatus' => 'expired'], 10, 250),
            edgeGroup('2026-10-30', ['cacheStatus' => 'dynamic'], 15, 200),
            edgeGroup('2026-10-30', ['cacheStatus' => 'bypass', 'edgeResponseStatus' => 200], 5, 150),
            edgeGroup('2026-10-30', ['cacheStatus' => 'bypass', 'edgeResponseStatus' => 301], 3, 150),
            edgeGroup('2026-10-30', ['cacheStatus' => 'none'], 6, -1),
            edgeGroup('2026-10-30', ['cacheStatus' => 'ignored'], 2, 100),
        ],
        'status' => [
            edgeGroup('2026-10-30', ['edgeResponseStatus' => 200], 80),
            edgeGroup('2026-10-30', ['edgeResponseStatus' => 301], 10),
            edgeGroup('2026-10-30', ['edgeResponseStatus' => 404], 12),
            edgeGroup('2026-10-30', ['edgeResponseStatus' => 503], 4),
        ],
        'agent' => [
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'GoogleBot'], 7),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'BingBot'], 6),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'AppleBot'], 5),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'YandexBot'], 3),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'ChromeHeadless'], 2),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'Unknown'], 50),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'Chrome'], 20),
            edgeGroup('2026-10-30', ['userAgentBrowser' => 'MobileSafari'], 13),
        ],
        'far' => [
            edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 30),
            edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 9),
        ],
    ]]]]])]);

    $result = FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-29'), Carbon::parse('2026-10-30'), $this->website->slug);

    expect($result)->toBe(['days' => 2, 'errors' => []]);

    $daily = WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'daily')->firstOrFail();
    $day   = $daily->records()->where('period', '2026-10-30')->firstOrFail();

    expect($day->only(FetchWebsiteEdgeTimeSeries::METRICS))->toBe([
        'requests'                    => 106,
        'requests_hit'                => 45,
        'requests_miss'               => 20,
        'requests_expired'            => 10,
        'requests_dynamic'            => 15,
        'requests_bypass'             => 8,
        'requests_bypass_redirect'    => 3,
        'requests_none'               => 6,
        'requests_other_cache_status' => 2,
        'origin_response_ms_total'    => 20 * 300 + 10 * 250 + 15 * 200 + 8 * 150 + 2 * 100,
        'requests_2xx'                => 80,
        'requests_3xx'                => 10,
        'requests_4xx'                => 12,
        'requests_5xx'                => 4,
        'requests_google_bot'         => 7,
        'requests_bing_bot'           => 6,
        'requests_apple_bot'          => 5,
        'requests_other_bots'         => 5,
        'requests_unknown_agent'      => 50,
        'requests_browsers'           => 33,
        'requests_far_countries'      => 39,
        'requests_far_countries_hit'  => 30,
    ])->and($daily->fresh()->number_records)->toBe(2);

    $weekly = WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'weekly')->firstOrFail()->records()->sole();
    expect($weekly->period)->toBe('2026 W44')
        ->and($weekly->requests)->toBe(206)
        ->and($weekly->requests_dynamic)->toBe(115)
        ->and($weekly->origin_response_ms_total)->toBe($day->origin_response_ms_total + 100 * 200);

    expect(WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'yearly')->firstOrFail()->records()->sole()->requests)->toBe(206);
});

it('asks for today so far but never the future or days cloudflare no longer keeps whole, and splits longer windows', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => []]]])]);

    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-09-01'), Carbon::parse('2026-11-02'), $this->website->slug);

    $windows = Http::recorded()->map(function (array $exchange) {
        /** @var Request $request */
        $request = $exchange[0];
        preg_match('/date_geq: "([\d-]+)", date_leq: "([\d-]+)"/', $request['query'], $matches);

        return [$matches[1], $matches[2], $request->hasHeader('Authorization', 'Bearer edge-test-token')];
    })->all();

    expect($windows)->toBe([
        ['2026-10-01', '2026-10-29', true],
        ['2026-10-30', '2026-10-31', true],
    ]);
});

it('re-fetches the last four days on the first run of the hour and only today on the other runs', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => []]]])]);

    $windowAt = function (string $time) {
        $this->travelTo(Carbon::parse("2026-10-31 $time", 'UTC'));
        $before = Http::recorded()->count();
        $this->artisan('websites:fetch_edge_time_series', ['--website' => $this->website->slug])->run();
        preg_match('/date_geq: "([\d-]+)", date_leq: "([\d-]+)"/', Http::recorded()->slice($before)->first()[0]['query'], $matches);

        return [$matches[1], $matches[2]];
    };

    expect($windowAt('12:00:00'))->toBe(['2026-10-27', '2026-10-31'])
        ->and($windowAt('12:05:00'))->toBe(['2026-10-31', '2026-10-31'])
        ->and($windowAt('12:55:00'))->toBe(['2026-10-31', '2026-10-31']);
});

it('reports cloudflare errors per batch and stores nothing for it', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response([
        'data'   => null,
        'errors' => [['message' => 'not authorized for that account']],
    ])]);

    $result = FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);

    expect($result['days'])->toBe(0)
        ->and($result['errors'])->toBe([$this->website->slug.' 2026-10-30..2026-10-30: not authorized for that account'])
        ->and(WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->exists())->toBeFalse();
});

it('gives the websites dashboard the edge days of the chosen website only', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 40, 0), edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 10, 300)],
        'status'  => [],
        'agent'   => [],
        'far'     => [],
    ]]]]])]);
    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);

    $traffic = GetWebsitesEdgeTraffic::run($this->organisation->group, $this->user, $this->website->slug, 30);
    $day     = collect($traffic['daily'])->firstWhere('date', '2026-10-30');

    expect($traffic['selected'])->toBe($this->website->slug)
        ->and($traffic['days'])->toBe(30)
        ->and($day['requests'])->toBe(50)
        ->and($day['requests_hit'])->toBe(40)
        ->and($day['origin_response_ms_total'])->toBe(3000)
        ->and(collect($traffic['options'][2]['items'])->pluck('value'))->toContain($this->website->slug);

    expect(GetWebsitesEdgeTraffic::run($this->organisation->group, $this->user, 'no-such-website', 7)['selected'])->toBe('all');
});

it('shows the website nav and the edge traffic overview to staff with group access', function () {
    $this->withoutVite();
    actingAs($this->user);
    expect($this->user->hasGroupAccess())->toBeTrue();

    $websiteNav = GetGroupNavigation::run($this->user)['website'];
    expect($websiteNav['label'])->toBe('WebOps')
        ->and($websiteNav['topMenu']['subSections'][0]['label'])->toBe('Web Cache');

    get(route('grp.websites.index', ['tab' => 'showcase', 'website' => $this->website->slug, 'days' => 60]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Web/WebsitesDashboard', false)
            ->where('showcase.selected', $this->website->slug)
            ->where('showcase.days', 60));

    get(route('grp.websites.index', ['tab' => 'showcase']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.selected', $this->website->slug)
            ->where('showcase.days', 60));

    get(route('grp.websites.index', ['tab' => 'showcase', 'website' => 'all', 'days' => 0]))->assertOk();
    expect($this->user->fresh()->settings['websites_dashboard'])->toEqual(['website' => 'all', 'days' => 0]);
});

it('retries a failed batch zone by zone and reports zones missing from the answer', function () {
    $other = Website::factory()->create([
        'organisation_id'    => $this->organisation->id,
        'shop_id'            => createShop()[2]->id,
        'group_id'           => $this->organisation->group_id,
        'type'               => WebsiteTypeEnum::B2C,
        'state'              => WebsiteStateEnum::LIVE,
        'status'             => true,
        'cloudflare_zone_id' => $otherZone = Str::random(32),
        'cloudflare_token'   => encrypt('edge-test-token'),
    ]);

    Http::fake(function (Request $request) use ($otherZone) {
        $asksFor = fn (string $zone) => str_contains($request['query'], $zone);

        return match (true) {
            $asksFor($this->zone) && $asksFor($otherZone) => Http::response(['data' => null, 'errors' => [['message' => 'one zone is not readable']]]),
            $asksFor($this->zone) => Http::response(['data' => ['viewer' => ['zones' => [[
                'zoneTag' => $this->zone,
                'cache'   => [edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 20, 0)],
                'status'  => [],
                'agent'   => [],
                'far'     => [],
            ]]]]]),
            default => Http::response(['data' => ['viewer' => ['zones' => []]]]),
        };
    });

    $result = FetchWebsiteEdgeTimeSeries::make()->handle(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'));

    expect($result['errors'])->toContain($other->slug." 2026-10-30..2026-10-30: zone not in Cloudflare's answer (zone id wrong, or the token cannot read its analytics)")
        ->and(WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'daily')->firstOrFail()->records()->sole()->requests)->toBe(20)
        ->and(WebsiteEdgeTimeSeries::where('website_id', $other->id)->exists())->toBeFalse();
});

it('rolls a week that spans new year into its iso week', function () {
    $this->travelTo(Carbon::parse('2027-01-05 12:00:00', 'UTC'));
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [edgeGroup('2026-12-29', ['cacheStatus' => 'miss'], 30, 0), edgeGroup('2027-01-02', ['cacheStatus' => 'miss'], 12, 0)],
        'status'  => [],
        'agent'   => [],
        'far'     => [],
    ]]]]])]);

    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-12-29'), Carbon::parse('2027-01-02'), $this->website->slug);

    $week = WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'weekly')->firstOrFail()->records()->sole();
    expect($week->period)->toBe('2026 W53')
        ->and($week->from->toDateString())->toBe('2026-12-28')
        ->and($week->requests)->toBe(42);
});

it('refuses a zone whose answer hit the row limit, and says when a website has no zone', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 20, 0)],
        'status'  => [],
        'agent'   => array_fill(0, 10000, edgeGroup('2026-10-30', ['userAgentBrowser' => 'Chrome'], 1)),
        'far'     => [],
    ]]]]])]);

    $result = FetchWebsiteEdgeTimeSeries::make()->handle(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'));

    expect($result['errors'])->toContain($this->website->slug." 2026-10-30..2026-10-30: agent hit Cloudflare's 10000 row limit, not stored; rerun with a shorter --from/--to")
        ->and(WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->exists())->toBeFalse();

    $this->website->update(['cloudflare_zone_id' => null]);

    expect(FetchWebsiteEdgeTimeSeries::make()->handle(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug)['errors'])
        ->toBe([$this->website->slug.' has no Cloudflare zone id or token']);
});

it('drills down to the websites of one day and to the visits of one country', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [
            edgeGroup('2026-10-29', ['cacheStatus' => 'miss'], 30, 200),
            edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 40, 0),
            edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 10, 300),
        ],
        'status'  => [edgeGroup('2026-10-30', ['edgeResponseStatus' => 404], 5)],
        'agent'   => [],
        'far'     => [],
    ]]]]])]);
    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-29'), Carbon::parse('2026-10-30'), $this->website->slug);
    $this->website->visitors()->create(['group_id' => $this->website->group_id, 'organisation_id' => $this->website->organisation_id, 'shop_id' => $this->website->shop_id, 'session_id' => Str::random(16), 'visitor_hash' => Str::random(16), 'ip_hash' => Str::random(16), 'device_type' => 'desktop', 'os' => 'macOS', 'browser' => 'Chrome', 'user_agent' => 'Mozilla/5.0', 'country_code' => 'SG', 'first_seen_at' => '2026-10-30 10:00:00', 'last_seen_at' => '2026-10-30 10:05:00', 'page_views' => 3]);

    $day = IndexWebsitesEdgeTraffic::make()->handle($this->organisation->group, $this->user, $this->website->slug, 30, '2026-10-30')->sole();
    expect($day->requests)->toBe(50)
        ->and((float) $day->edge_share)->toBe(80.0)
        ->and((float) $day->share_4xx)->toBe(10.0)
        ->and((int) $day->avg_origin_ms)->toBe(300)
        ->and(IndexWebsitesEdgeTraffic::make()->handle($this->organisation->group, $this->user, $this->website->slug, 30, null)->sole()->requests)->toBe(80);

    $visits = IndexWebsiteVisitorCountries::make()->handle($this->organisation->group, $this->user, $this->website->slug, 30, null, 'SG')->sole();
    expect($visits->country_code)->toBe('SG')
        ->and((int) $visits->sessions)->toBe(1)
        ->and(IndexWebsiteVisitorCountries::make()->handle($this->organisation->group, $this->user, $this->website->slug, 30, null, 'GB')->total())->toBe(0);

    $this->withoutVite();
    actingAs($this->user);
    get(route('grp.websites.index', ['tab' => 'edge_websites', 'website' => $this->website->slug, 'day' => '2026-10-30', 'edge_websites_sort' => '-share_4xx']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('edge_filters.day', '2026-10-30')
            ->where('edge_filters.website_label', $this->website->domain)
            ->where('edge_websites.data.0.requests', 50));
    get(route('grp.websites.index', ['tab' => 'visitor_countries', 'country' => 'SG']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('edge_filters.country', 'SG')->has('visitor_countries.data', 1));
});

it('counts octane storefront pages that came through cloudflare and stores whole counted days', function () {
    Redis::del(CountOctaneStorefrontPage::key(Carbon::parse('2026-10-28')), CountOctaneStorefrontPage::key(Carbon::parse('2026-10-29')), CountOctaneStorefrontPage::key(Carbon::parse('2026-10-30')), CountOctaneStorefrontPage::key(Carbon::parse('2026-10-31')));

    $page = fn (bool $viaCloudflare, string $type = 'text/html; charset=UTF-8') => (new CountOctaneStorefrontPage())->terminate(
        tap(HttpRequest::create('/'), function ($request) use ($viaCloudflare) {
            $request->merge(['website' => $this->website]);
            if ($viaCloudflare) {
                $request->headers->set('CF-Ray', '8f00-SIN');
            }
        }),
        new Response('', 200, ['Content-Type' => $type])
    );
    $page(true);
    $page(true);
    $page(false);
    $page(true, 'application/json');
    expect(Redis::hget(CountOctaneStorefrontPage::key(now('UTC')), (string) $this->website->id))->toBe('2');

    Redis::hset(CountOctaneStorefrontPage::key(Carbon::parse('2026-10-29')), (string) $this->website->id, 3);
    Redis::hset(CountOctaneStorefrontPage::key(Carbon::parse('2026-10-30')), (string) $this->website->id, 7);
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag' => $this->zone,
        'cache'   => [edgeGroup('2026-10-29', ['cacheStatus' => 'dynamic'], 20, 100), edgeGroup('2026-10-30', ['cacheStatus' => 'dynamic'], 10, 100)],
        'status'  => [],
        'agent'   => [],
        'far'     => [],
    ]]]]])]);
    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-29'), Carbon::parse('2026-10-30'), $this->website->slug);

    $daily = WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'daily')->firstOrFail()->records()->pluck('requests_octane', 'period');
    expect($daily['2026-10-30'])->toBe(7)
        ->and($daily['2026-10-29'])->toBeNull()
        ->and(WebsiteEdgeTimeSeries::where('website_id', $this->website->id)->where('frequency', 'monthly')->firstOrFail()->records()->sole()->requests_octane)->toBeNull()
        ->and(IndexWebsitesEdgeTraffic::make()->handle($this->organisation->group, $this->user, $this->website->slug, 30, '2026-10-30')->sole()->requests_varnish)->toBe(3);

    Redis::del(CountOctaneStorefrontPage::key(Carbon::parse('2026-10-29')), CountOctaneStorefrontPage::key(Carbon::parse('2026-10-30')), CountOctaneStorefrontPage::key(Carbon::parse('2026-10-31')));
});

it('ignores crafted drill-down parameters instead of failing', function () {
    $this->withoutVite();
    actingAs($this->user);

    get(route('grp.websites.index', ['tab' => 'edge_websites', 'day' => '2026-13-40', 'website' => ['x'], 'country' => ['y']]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('edge_filters.day', null)->where('edge_filters.days', 30)->where('edge_filters.website', 'all')->where('edge_filters.country', null));
});

it('offers today so far, 24 hours and a week as yesterday and the last seven whole days, and falls back to 30 days', function () {
    Carbon::setTestNow('2026-11-01 10:00:00');

    $action = new GetWebsitesEdgeTraffic();

    expect(array_map(fn ($date) => $date instanceof Carbon ? $date->toDateTimeString() : $date, $action->window(1)))->toBe(['2026-10-31 00:00:00', '2026-10-31 23:59:59', 1])
        ->and($action->window(7)[0]->toDateString())->toBe('2026-10-25')
        ->and($action->window(5)[2])->toBe(30)
        ->and($action->window(null)[2])->toBe(30)
        ->and(array_map(fn ($date) => $date instanceof Carbon ? $date->toDateTimeString() : $date, $action->window(0)))->toBe(['2026-11-01 00:00:00', '2026-11-01 23:59:59', 0])
        ->and($action->window(1, '2026-11-01')[0]->toDateString())->toBe('2026-10-31');

    Carbon::setTestNow();
});

it('stores each cloudflare datacenter per day, keeps the website days when datacenters hit the row limit, and lists them by datacenter', function () {
    $zone = fn (array $colo) => ['data' => ['viewer' => ['zones' => [[
        'zoneTag'  => $this->zone,
        'cache'    => [edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 30, 0), edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 70, 300)],
        'status'   => [],
        'agent'    => [],
        'far'      => [],
        ...$colo,
    ]]]]];

    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::sequence()
        ->push($zone([
            'colo'     => [edgeGroup('2026-10-30', ['coloCode' => 'LHR'], 80), edgeGroup('2026-10-30', ['coloCode' => 'SIN'], 20)],
            'coloHit'  => [edgeGroup('2026-10-30', ['coloCode' => 'LHR'], 30)],
            'coloNone' => [edgeGroup('2026-10-30', ['coloCode' => 'SIN'], 5)],
        ]))
        ->push($zone(['colo' => array_fill(0, 10000, edgeGroup('2026-10-30', ['coloCode' => 'FRA'], 1))]))
        ->push($zone(['colo' => [edgeGroup('2026-10-30', ['coloCode' => 'LHR'], 75)], 'coloHit' => [edgeGroup('2026-10-30', ['coloCode' => 'LHR'], 30)], 'coloNone' => []]))]);
    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);

    expect(Http::recorded()->first()[0]->data()['query'])->toContain('coloCode')
        ->toContain('OR: [{cacheStatus_neq: "none"}, {edgeResponseStatus_notin: [301, 302, 303, 307, 308]}]')
        ->toContain('clientRequestHTTPMethodName_in: ["GET", "HEAD"]');

    $datacenters = IndexWebsitesEdgeDatacenters::make()->handle($this->organisation->group, $this->user, $this->website->slug, 7, null);
    $london      = $datacenters->firstWhere('colo', 'LHR');
    $singapore   = $datacenters->firstWhere('colo', 'SIN');
    expect($datacenters->total())->toBe(2)
        ->and($datacenters->first()->colo)->toBe('LHR')
        ->and([(int) $london->requests, (int) $london->requests_hit, (float) $london->hit_share, (int) $london->requests_reached])->toBe([80, 30, 37.5, 50])
        ->and([(int) $singapore->requests_none, (int) $singapore->requests_reached, (float) $singapore->hit_share])->toBe([5, 15, 0.0]);

    $result = FetchWebsiteEdgeTimeSeries::make()->handle(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);

    expect($result['days'])->toBe(1)
        ->and($result['errors'])->toBe([$this->website->slug." 2026-10-30..2026-10-30: datacenters hit Cloudflare's 10000 row limit, not stored; rerun with a shorter --from/--to"])
        ->and(WebsiteEdgeDatacenterDay::where('website_id', $this->website->id)->where('colo', 'FRA')->exists())->toBeFalse()
        ->and(WebsiteEdgeDatacenterDay::where('website_id', $this->website->id)->where('colo', 'LHR')->value('requests'))->toBe(80);

    FetchWebsiteEdgeTimeSeries::make()->handle(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);
    expect(WebsiteEdgeDatacenterDay::where('website_id', $this->website->id)->pluck('requests', 'colo')->all())->toBe(['LHR' => 75]);

    $this->withoutVite();
    actingAs($this->user);
    get(route('grp.websites.index', ['tab' => 'edge_datacenters', 'website' => $this->website->slug, 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('edge_filters.days', 7)->where('edge_filters.days_options', [0, 1, 7, 30, 60, 90, 365])->where('edge_datacenters.data.0.colo', 'LHR')->where('edge_datacenters.data.0.country_code', 'GB')->where('edge_datacenters.data.0.location', 'London, United Kingdom')->where('edge_datacenters.data.0.city', 'London')->where('edge_datacenters.data.0.requests_share', 100)->where('edge_datacenters.data.0.hit_share', 40));
});

it('stores each visitor country per day and shows its edge copy share beside the top countries', function () {
    Http::fake(['api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
        'zoneTag'    => $this->zone,
        'cache'      => [edgeGroup('2026-10-30', ['cacheStatus' => 'hit'], 30, 0), edgeGroup('2026-10-30', ['cacheStatus' => 'miss'], 70, 300)],
        'status'     => [],
        'agent'      => [],
        'far'        => [],
        'country'    => [edgeGroup('2026-10-30', ['clientCountryName' => 'SG'], 80), edgeGroup('2026-10-30', ['clientCountryName' => 'GB'], 20)],
        'countryHit' => [edgeGroup('2026-10-30', ['clientCountryName' => 'SG'], 60)],
    ]]]]])]);
    FetchWebsiteEdgeTimeSeries::run(Carbon::parse('2026-10-30'), Carbon::parse('2026-10-30'), $this->website->slug);

    expect(Http::recorded()->first()[0]->data()['query'])->toContain('clientCountryName')
        ->and(WebsiteEdgeCountryDay::where('website_id', $this->website->id)->orderBy('country')->get(['country', 'requests', 'requests_hit'])->toArray())
        ->toBe([['country' => 'GB', 'requests' => 20, 'requests_hit' => 0], ['country' => 'SG', 'requests' => 80, 'requests_hit' => 60]]);

    $this->website->visitors()->create(['group_id' => $this->website->group_id, 'organisation_id' => $this->website->organisation_id, 'shop_id' => $this->website->shop_id, 'session_id' => Str::random(16), 'visitor_hash' => Str::random(16), 'ip_hash' => Str::random(16), 'device_type' => 'desktop', 'os' => 'macOS', 'browser' => 'Chrome', 'user_agent' => 'Mozilla/5.0', 'country_code' => 'SG', 'first_seen_at' => '2026-10-30 10:00:00', 'last_seen_at' => '2026-10-30 10:05:00', 'page_views' => 3]);
    $this->website->visitors()->create(['group_id' => $this->website->group_id, 'organisation_id' => $this->website->organisation_id, 'shop_id' => $this->website->shop_id, 'session_id' => Str::random(16), 'visitor_hash' => Str::random(16), 'ip_hash' => Str::random(16), 'device_type' => 'desktop', 'os' => 'macOS', 'browser' => 'Chrome', 'user_agent' => 'Mozilla/5.0', 'country_code' => 'FR', 'first_seen_at' => '2026-10-30 11:00:00', 'last_seen_at' => '2026-10-30 11:05:00', 'page_views' => 1]);

    $countries = collect(GetWebsitesEdgeTraffic::run($this->organisation->group, $this->user, $this->website->slug, 7)['countries'])->keyBy('code');
    expect([$countries['SG']['edge_requests'], $countries['SG']['edge_requests_hit']])->toBe([80, 60])
        ->and($countries['FR']['edge_requests'])->toBeNull();
});

it('flags an edge redirect that differs from what our servers answer, or that loops', function () {
    $answers = [
        'https://shop.test/ok'        => ['edge' => 'https://shop.test/page', 'origin' => 'https://shop.test/page'],
        'https://shop.test/page'      => ['edge' => null, 'origin' => null],
        'https://shop.test/stale'     => ['edge' => 'https://shop.test/old', 'origin' => '/new'],
        'https://shop.test/a'         => ['edge' => '/b', 'origin' => '/b'],
        'https://shop.test/b'         => ['edge' => 'https://shop.test/a', 'origin' => 'https://shop.test/a'],
    ];
    Http::fake(function (Request $request) use ($answers) {
        $side     = $request->header('User-Agent')[0] === MonitorEdgeRedirects::ORIGIN_AGENT ? 'origin' : 'edge';
        $location = $answers[$request->url()][$side];

        return $location === null ? Http::response('page') : Http::response('', 301, ['Location' => $location]);
    });

    $monitor = new MonitorEdgeRedirects();

    expect($monitor->check('https://shop.test/ok'))->toBeNull()
        ->and($monitor->check('https://shop.test/stale'))->toBe('Cloudflare sends to https://shop.test/old, our servers to https://shop.test/new')
        ->and($monitor->check('https://shop.test/a'))->toBe('redirect loop: https://shop.test/a -> https://shop.test/b -> https://shop.test/a');
});
