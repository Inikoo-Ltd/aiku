<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 13:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Iris\Docs\PurgeIrisDocsFromVarnish;
use App\Actions\Iris\Docs\ShowIrisDoc;
use App\Actions\Iris\Docs\ShowIrisDocs;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Helpers\Language;
use App\Models\Web\Webpage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Actions\Web\Website\Cloudflare\FetchFirewallBlockedCountryEvents;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareUrl;
use App\Http\Middleware\DetectIrisWebsite;
use App\Models\DevOps\AppDeployment;
use App\Http\Middleware\DetectWebsite;
use App\Models\Web\Website;
use Illuminate\Http\Request;
use App\Actions\Web\Webpage\Iris\ShowIrisFavicon;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Actions\Comms\Email\SendAppointmentEmail;
use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\Iris\Appointment\ShowIrisAppointment;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Actions\CRM\AppointmentType\StoreAppointmentType;
use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Http\Middleware\HandleIrisInertiaRequests;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Actions\CRM\Appointment\Widget\AppointmentWidgetKey;
use App\Actions\CRM\Appointment\Widget\EnableShopAppointmentWidget;
use App\Models\CRM\Appointment;
use App\Models\CRM\Prospect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Actions\Retina\Dropshipping\Product\SaveDataFeeds;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Support\Str;
use App\Actions\Comms\Outbox\ReviewReminder\ProcessReviewReminderRecipients;
use App\Actions\Iris\Reviews\ShowIrisOrderReview;
use App\Actions\Iris\Reviews\ShowIrisOrderReviewPreview;
use App\Actions\Comms\Outbox\GetOutboxTestEmailData;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use Inertia\Testing\AssertableInertia;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Reviews\Review;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop = createShop($this->organisation)[2];
    $this->website = createWebsite($this->shop);
    $this->website->update(['status' => true]);
});

test('it detects website', function () {
    DetectWebsiteFromDomain::shouldRun()->andReturn($this->website);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectWebsite();

    $response = $middleware->handle($request, function ($req) {
        expect($req->input('website'))->toBeInstanceOf(Website::class)
            ->and($req->input('website')->id)->toBe($this->website->id)
            ->and($req->input('domain'))->toBe($this->website->domain);

        return response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('it processes blocked country regions in DetectWebsite', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['cities' => ['New York']],
            'GB' => ['postcode' => ['SW1A 1AA']],
            'FR' => ['something_else' => 'value']
        ]
    ]);

    DetectWebsiteFromDomain::shouldRun()->andReturn($this->website);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectWebsite();

    $middleware->handle($request, function ($req) {
        expect($req->attributes->get('has_blocked_country_regions'))->toBeTrue()
            ->and($req->attributes->get('blocked_countries'))->toHaveCount(2)
            ->and($req->attributes->get('blocked_countries'))->toContain('US', 'GB')
            ->and($req->attributes->get('blocked_country_regions'))->toEqual($this->website->blocked_country_regions)
            ->and($req->query->has('has_blocked_country_regions'))->toBeFalse();

        return response('OK');
    });
});

test('iris footer json includes app version', function () {
    AppDeployment::create(['commit_hash' => 'abc123', 'semantic_version' => 'v2.369.0']);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $response = $this->getJson('http://' . $this->website->domain . '/json/footer');
    $response->assertOk()
        ->assertJsonPath('version', 'v2.369.0');
});

test('iris shares the out of stock notification outbox only when a page renders, never on json calls', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);

    $outboxQueries          = 0;
    $appointmentTypeQueries = 0;
    DB::listen(function ($query) use (&$outboxQueries, &$appointmentTypeQueries) {
        if (str_contains($query->sql, 'from "outboxes"')) {
            $outboxQueries++;
        }
        if (str_contains($query->sql, 'from "appointment_types"')) {
            $appointmentTypeQueries++;
        }
    });

    $this->getJson('http://'.$this->website->domain.'/json/footer')->assertOk();
    expect($outboxQueries)->toBe(0)
        ->and($appointmentTypeQueries)->toBe(0);

    $request = Request::create('http://'.$this->website->domain);
    $request->merge(['website' => $this->website]);
    $request->setLaravelSession(app('session.store'));
    $props = (new \App\Http\Middleware\HandleIrisInertiaRequests())->share($request);
    $outboxQueriesWhileSharing = $outboxQueries;

    $expected = $this->shop->outboxes()
        ->where('code', OutboxCodeEnum::OOS_NOTIFICATION)
        ->get()
        ->mapWithKeys(fn ($outbox) => [$outbox->code->value => ['id' => $outbox->id, 'state' => $outbox->state]])
        ->all();

    expect($outboxQueriesWhileSharing)->toBe(0)
        ->and($props['outboxes'])->toBeInstanceOf(\Closure::class)
        ->and($props['iris'])->toBeInstanceOf(\Closure::class)
        ->and($props['outboxes']())->toBe($expected)
        ->and($appointmentTypeQueries)->toBe(0)
        ->and($props['appointment_types']())->toBe(
            $this->shop->appointmentTypes()->where('is_active', true)->orderBy('name')->get()
                ->map(fn ($appointmentType) => ['id' => $appointmentType->id, 'name' => $appointmentType->name, 'meeting_mode' => $appointmentType->meeting_mode->value])
                ->all()
        )
        ->and($props['announcements']())->toBe(\App\Actions\Web\Announcement\UI\GetIrisAnnouncements::run($this->website))
        ->and($props['website_dialogs']())->toBe(\App\Actions\Web\WebsiteDialog\UI\GetIrisWebsiteDialogs::run($this->website));
});

test('iris caches the website with its organisation and group so a request does not query them again', function () {
    $websiteData = (new DetectIrisWebsite())->getWebsiteData($this->website->domain);
    $cached      = unserialize(serialize($websiteData['website']));

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($cached->organisation->id)->toBe($this->website->organisation_id)
        ->and($cached->group->id)->toBe($this->website->group_id)
        ->and($cached->shop->id)->toBe($this->website->shop_id)
        ->and($queries)->toBe(0);
});

test('iris reads the cached website as it is written today and deflated, and gets the same website either way', function () {
    config(['iris.cache.website.ttl' => 3600, 'cache.stores.array.serialize' => true]);
    Cache::purge();
    $middleware = new DetectIrisWebsite();
    $domain     = $this->website->domain;
    $key        = config('iris.cache.website.prefix').'_domain:'.$domain;

    $websiteData  = $middleware->getWebsiteData($domain);
    $servedOnAHit = serialize(unserialize(serialize($websiteData)));

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    Cache::put($key, $websiteData, 3600);
    $fromArrayEntry      = $middleware->getCachedWebsiteData($domain);
    $arrayEntryAfterRead = Cache::get($key);

    $deflated = $middleware->compressWebsiteData($websiteData);
    Cache::put($key, $deflated, 3600);
    $fromDeflatedEntry = $middleware->getCachedWebsiteData($domain);

    expect($fromArrayEntry['website'])->not->toBe($websiteData['website'])
        ->and($arrayEntryAfterRead)->toBeArray()
        ->and(serialize($fromArrayEntry))->toBe($servedOnAHit)
        ->and(serialize($fromDeflatedEntry))->toBe($servedOnAHit)
        ->and($fromDeflatedEntry)->toEqual($websiteData)
        ->and($middleware->decompressWebsiteData($deflated))->toEqual($websiteData)
        ->and(strlen($deflated))->toBeLessThan(strlen(serialize($websiteData)))
        ->and($fromDeflatedEntry['website']->organisation->id)->toBe($this->website->organisation_id)
        ->and($fromDeflatedEntry['website']->group->id)->toBe($this->website->group_id)
        ->and($fromDeflatedEntry['website']->shop->id)->toBe($this->website->shop_id)
        ->and(Cache::get($key))->toBe($deflated)
        ->and($queries)->toBe(0);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($domain);

    $response = $middleware->handle(Request::create('http://'.$domain), function ($request) use ($websiteData) {
        expect($request->input('website'))->toEqual($websiteData['website'])
            ->and($request->input('domain'))->toBe($websiteData['domain'])
            ->and($request->input('currency_data'))->toBe($websiteData['currency_data'])
            ->and($request->input('locale'))->toBe($websiteData['locale'])
            ->and($request->input('favicons'))->toBe($websiteData['favicons']);

        return response('OK');
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Cache-Tag'))->toBe('aiku-website-'.$this->website->id)
        ->and($queries)->toBe(0);

    Cache::forget($key);
});

test('iris rebuilds a cached website it cannot read, writes it deflated and caches nothing for an unknown domain', function () {
    config(['iris.cache.website.ttl' => 3600]);
    $middleware = new DetectIrisWebsite();
    $domain     = $this->website->domain;
    $key        = config('iris.cache.website.prefix').'_domain:'.$domain;

    $websiteData = $middleware->getWebsiteData($domain);

    Cache::forget($key);
    $onAMiss        = $middleware->getCachedWebsiteData($domain);
    $writtenOnAMiss = Cache::get($key);

    Cache::put($key, 'not a deflated website', 3600);
    $fromUnreadableEntry        = $middleware->getCachedWebsiteData($domain);
    $writtenOverUnreadableEntry = Cache::get($key);

    Cache::put($key, substr($middleware->compressWebsiteData($websiteData), 0, -40), 3600);
    $fromTruncatedEntry = $middleware->getCachedWebsiteData($domain);

    expect($onAMiss)->toEqual($websiteData)
        ->and($writtenOnAMiss)->toBeString()
        ->and($middleware->decompressWebsiteData($writtenOnAMiss))->toEqual($websiteData)
        ->and($fromUnreadableEntry)->toEqual($websiteData)
        ->and($writtenOverUnreadableEntry)->toBeString()
        ->and($middleware->decompressWebsiteData($writtenOverUnreadableEntry))->toEqual($websiteData)
        ->and($fromTruncatedEntry)->toEqual($websiteData)
        ->and($middleware->decompressWebsiteData(Cache::get($key)))->toEqual($websiteData)
        ->and($middleware->decompressWebsiteData('not a deflated website'))->toBeNull()
        ->and($middleware->decompressWebsiteData(gzcompress(serialize('not an array'))))->toBeNull();

    $unknownDomain = 'unknown-'.Str::random(8).'.test';
    expect(fn () => $middleware->getCachedWebsiteData($unknownDomain))->toThrow(NotFoundHttpException::class)
        ->and(Cache::has(config('iris.cache.website.prefix').'_domain:'.$unknownDomain))->toBeFalse();

    Cache::forget($key);
});

test('webpage stats are indexed by webpage', function () {
    $indexes = collect(DB::select('SELECT indexdef FROM pg_indexes WHERE tablename = ?', ['webpage_stats']))->pluck('indexdef')->implode("\n");

    expect($indexes)->toContain('UNIQUE INDEX webpage_stats_webpage_id_unique');
});

test('live webpages are indexed by their canonical path', function () {
    $indexes = collect(DB::select('SELECT indexdef FROM pg_indexes WHERE tablename = ?', ['webpages']))->pluck('indexdef')->implode("\n");

    expect($indexes)->toContain('webpages_site_canonical_path_live_idx');
});

test('it detects iris website', function () {
    // Mock parseDomain to return the website's domain
    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectIrisWebsite();

    $response = $middleware->handle($request, function ($req) {
        expect($req->input('website'))->toBeInstanceOf(Website::class)
            ->and($req->input('website')->id)->toBe($this->website->id)
            ->and($req->input('domain'))->toBe($this->website->domain);

        return response('OK');
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-AIKU-WEBSITE'))->toBe((string) $this->website->id)
        ->and($response->headers->get('Cache-Tag'))->toBe('aiku-website-'.$this->website->id);
});

test('it processes blocked country regions in DetectIrisWebsite', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['cities' => ['New York']],
            'GB' => ['postcode' => ['SW1A 1AA']],
            'FR' => ['something_else' => 'value'] // Should not be in blocked_countries
        ]
    ]);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectIrisWebsite();

    $middleware->handle($request, function ($req) {
        expect($req->attributes->get('has_blocked_country_regions'))->toBeTrue()
            ->and($req->attributes->get('blocked_countries'))->toHaveCount(2)
            ->and($req->attributes->get('blocked_countries'))->toContain('US', 'GB')
            ->and($req->attributes->get('blocked_country_regions'))->toEqual($this->website->blocked_country_regions)
            ->and($req->query->has('has_blocked_country_regions'))->toBeFalse();

        return response('OK');
    });
});

test('it blocks a visitor from a restricted region end to end', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['postcode' => '/^100/']
        ]
    ]);

    DB::table('ip_geolocations')->updateOrInsert(
        ['ip' => '5.6.7.8'],
        [
            'country'    => 'US',
            'city'       => 'New York',
            'postcode'   => '10001',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $request = Request::create(
        'http://' . $this->website->domain,
        'GET',
        server: ['REMOTE_ADDR' => '5.6.7.8', 'HTTP_CF_IPCOUNTRY' => 'US']
    );

    $middleware = new DetectIrisWebsite();

    $middleware->handle($request, function ($req) {
        expect(\App\Actions\Web\Website\BlockedCountries\CheckIfCountryRegionsIsBlocked::run($req))->toBeTrue();

        return response('OK');
    });
});

test('it blocks a json request from a restricted region in retina', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['postcode' => '/^100/']
        ]
    ]);

    DB::table('ip_geolocations')->updateOrInsert(
        ['ip' => '5.6.7.8'],
        [
            'country'    => 'US',
            'city'       => 'New York',
            'postcode'   => '10001',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DetectWebsiteFromDomain::shouldRun()->andReturn($this->website);

    $request = Request::create(
        'http://' . $this->website->domain . '/app/models/order/1/submit',
        'PATCH',
        server: [
            'REMOTE_ADDR'       => '5.6.7.8',
            'HTTP_CF_IPCOUNTRY' => 'US',
            'HTTP_ACCEPT'       => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]
    );

    $detect = new DetectWebsite();
    $restrict = new App\Http\Middleware\RestrictCountryRegions();

    expect(fn () => $detect->handle($request, fn ($req) => $restrict->handle($req, fn () => response('OK'))))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('it logs firewall blocked country events fetched from Cloudflare', function () {
    $this->website->update([
        'cloudflare_zone_id'      => 'zone123',
        'cloudflare_token'        => encrypt('test-api-token'),
        'migrated'                => true,
        'blocked_country_regions' => ['US' => ['postcode' => '10001']],
    ]);

    $geolocationId = DB::table('ip_geolocations')->insertGetId([
        'ip'         => '1.2.3.4',
        'country'    => 'US',
        'city'       => 'New York',
        'postcode'   => '10001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone123/rulesets?phase=http_request_firewall_custom' => Http::response([
            'result' => [
                ['id' => 'ruleset123', 'kind' => 'zone'],
            ],
        ]),
        'api.cloudflare.com/client/v4/zones/zone123/rulesets/ruleset123' => Http::response([
            'result' => [
                'rules' => [
                    ['id' => 'rule123', 'description' => 'Block countries (aiku)'],
                ],
            ],
        ]),
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => [
                'viewer' => [
                    'zones' => [
                        [
                            'firewallEventsAdaptive' => [
                                [
                                    'action'            => 'block',
                                    'clientIP'          => '1.2.3.4',
                                    'clientCountryName' => 'US',
                                    'datetime'          => now()->toIso8601String(),
                                    'ruleId'            => 'rule123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    FetchFirewallBlockedCountryEvents::run();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/graphql'
        && str_contains($request->body(), 'rule123'));

    expect(DB::table('restricted_country_region_logs')
        ->where('ip_geolocation_id', $geolocationId)
        ->where('was_blocked', true)
        ->exists())->toBeTrue();
});

test('it skips fetching firewall events when no block-countries rule exists on Cloudflare', function () {
    $this->website->update([
        'cloudflare_zone_id'      => 'zone456',
        'cloudflare_token'        => encrypt('test-api-token'),
        'migrated'                => true,
        'blocked_country_regions' => ['US' => ['postcode' => '10001']],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone456/rulesets?phase=http_request_firewall_custom' => Http::response([
            'result' => [
                ['id' => 'ruleset456', 'kind' => 'zone'],
            ],
        ]),
        'api.cloudflare.com/client/v4/zones/zone456/rulesets/ruleset456' => Http::response([
            'result' => [
                'rules' => [],
            ],
        ]),
        'api.cloudflare.com/client/v4/graphql' => Http::response([]),
    ]);

    FetchFirewallBlockedCountryEvents::run();

    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/graphql');
});

test('it advances the fetch cursor so re-running does not re-query the same event window', function () {
    $this->website->update([
        'cloudflare_zone_id'      => 'zone789',
        'cloudflare_token'        => encrypt('test-api-token'),
        'migrated'                => true,
        'blocked_country_regions' => ['US' => ['postcode' => '10001']],
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone789/rulesets?phase=http_request_firewall_custom' => Http::response([
            'result' => [
                ['id' => 'ruleset789', 'kind' => 'zone'],
            ],
        ]),
        'api.cloudflare.com/client/v4/zones/zone789/rulesets/ruleset789' => Http::response([
            'result' => [
                'rules' => [
                    ['id' => 'rule789', 'description' => 'Block countries (aiku)'],
                ],
            ],
        ]),
        'api.cloudflare.com/client/v4/graphql' => Http::response([
            'data' => ['viewer' => ['zones' => [['firewallEventsAdaptive' => []]]]],
        ]),
    ]);

    Carbon::setTestNow('2026-07-01 10:00:00');
    FetchFirewallBlockedCountryEvents::run();

    Carbon::setTestNow('2026-07-01 11:00:00');
    FetchFirewallBlockedCountryEvents::run();

    Carbon::setTestNow();

    $graphqlRequests = Http::recorded(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/graphql')->values();

    expect($graphqlRequests)->toHaveCount(2);

    $firstQuery  = json_decode($graphqlRequests[0][0]->body(), true)['query'];
    $secondQuery = json_decode($graphqlRequests[1][0]->body(), true)['query'];

    preg_match('/datetime_geq: "([^"]+)"/', $firstQuery, $firstSince);
    preg_match('/datetime_geq: "([^"]+)"/', $secondQuery, $secondSince);

    expect(Carbon::parse($secondSince[1]))->toEqual(Carbon::parse('2026-07-01 10:00:00')->subMinutes(15))
        ->and(Carbon::parse($secondSince[1]))->toBeGreaterThan(Carbon::parse($firstSince[1]));
});

test('iris serves the website favicon at the root favicon.ico', function () {
    $response = $this->get('http://'.$this->website->domain.'/favicon.ico');

    $response->assertRedirect(url('favicons/iris-favicon-48x48.png'));
});

test('aiku own domains keep serving the aiku favicon at the root favicon.ico', function () {
    $response = $this->get('http://app.'.config('app.domain').'/favicon.ico');

    $response->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('it purges a url in cloudflare for both apex and www', function () {
    $this->website->update([
        'cloudflare_zone_id' => 'zone123',
        'cloudflare_token'   => encrypt('token123'),
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone123/purge_cache' => Http::response(['success' => true]),
    ]);

    expect(PurgeCloudflareUrl::run($this->website, '/favicon.ico'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request['files'] === [
            'https://'.$this->website->domain.'/favicon.ico',
            'https://www.'.$this->website->domain.'/favicon.ico',
        ];
    });
});

test('iris error pages carry the ziggy route list so the search bar can build urls', function () {
    $originalRequest = app('request');
    $request         = Request::create('http://'.$this->website->domain.'/no-such-page', 'GET');
    app()->instance('request', $request);
    app()->detectEnvironment(fn () => 'production');

    try {
        $response = app(\App\Exceptions\Handler::class)->render($request, new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException());
    } finally {
        app()->detectEnvironment(fn () => 'testing');
        app()->instance('request', $originalRequest);
    }

    preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $page = json_decode($matches[1], true);

    expect($response->getStatusCode())->toBe(404)
        ->and($page['component'])->toBe('Errors/Error')
        ->and($page['props']['ziggy']['routes'])->toHaveKey('iris.json.search.catalogue')
        ->and($page['props']['ziggy']['location'])->toBe('http://'.$this->website->domain.'/no-such-page');
});

test('iris streams a product ingredients label pdf only for its own shop products with ingredients', function () {
    [, $product] = createProduct($this->shop);
    $url = 'http://'.$this->website->domain.'/catalogue/product/'.$product->slug.'/ingredients-label.pdf';

    $product->updateQuietly(['marketing_ingredients' => null]);
    $this->get($url)->assertNotFound();

    $product->updateQuietly(['marketing_ingredients' => 'Aqua, Glycerin', 'marketing_weight' => 100]);
    $response = $this->get($url);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain($product->code.'_unit_ingredients.pdf');

    $otherShop = createOwnShop('ingredients-label-other-shop')[2];
    $product->updateQuietly(['shop_id' => $otherShop->id]);
    $this->get($url)->assertNotFound();
});

test('iris attachment routes serve public product documents to anyone holding the link (PRE-67) and labeling guides to logged-in customers only (HELP-4214)', function () {
    Storage::fake('local');
    [, $product] = createProduct($this->shop);
    $customer    = createCustomer($this->shop);
    $baseUrl     = 'http://'.$this->website->domain.'/attachment/';

    $productSds        = createAttachedMedia('Product', $product->id, 'sds');
    $tradeUnitIfra     = createAttachedMedia('TradeUnit', 1, 'ifra');
    $labelingGuide     = createAttachedMedia('TradeUnitFamily', 1, 'labeling_guide');
    $webpageAttachment = createAttachedMedia('Webpage', 1, 'webpage');
    $privateSds        = createAttachedMedia('TradeUnit', 1, 'sds_private');
    $employeeContract  = createAttachedMedia('Employee', 1, 'Contract');
    $customerNote      = createAttachedMedia('Customer', $customer->id, 'CustomerNote');

    $this->get($baseUrl.$labelingGuide->ulid)->assertNotFound();
    $this->get($baseUrl.$labelingGuide->ulid.'/download')->assertNotFound();

    foreach ([$productSds, $tradeUnitIfra, $webpageAttachment] as $media) {
        $this->get($baseUrl.$media->ulid.'/download')->assertOk();
    }

    foreach ([$privateSds, $employeeContract, $customerNote] as $media) {
        $this->get($baseUrl.$media->ulid.'/download')->assertNotFound();
    }

    actingAs(createWebUser($customer), 'retina');

    foreach ([$productSds, $tradeUnitIfra, $labelingGuide, $webpageAttachment] as $media) {
        $this->get($baseUrl.$media->ulid)->assertOk();
        $this->get($baseUrl.$media->ulid.'/download')->assertOk();
    }

    foreach ([$privateSds, $employeeContract, $customerNote] as $media) {
        $this->get($baseUrl.$media->ulid)->assertNotFound();
        $this->get($baseUrl.$media->ulid.'/download')->assertNotFound();
    }
});

test('retina attachment download serves public documents and the customer own order files only', function () {
    Storage::fake('local');
    [, $product]   = createProduct($this->shop);
    $customer      = createCustomer($this->shop);
    $otherCustomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $webUser       = createWebUser($customer);

    $order      = createOrder($customer, $product);
    $orderFile  = createAttachedMedia('Order', $order->id, 'Other');
    $productSds = createAttachedMedia('Product', $product->id, 'sds');
    $employeeCv = createAttachedMedia('Employee', 1, 'CV');

    DetectWebsiteFromDomain::mock()->shouldReceive('handle')->andReturn($this->website);
    actingAs($webUser, 'retina');

    $this->get(route('retina.models.attachment.download', $orderFile->ulid))->assertOk();
    $this->get(route('retina.models.attachment.download', $productSds->ulid))->assertOk();
    $this->get(route('retina.models.attachment.download', $employeeCv->ulid))->assertNotFound();

    $order->updateQuietly(['customer_id' => $otherCustomer->id]);
    $this->get(route('retina.models.attachment.download', $orderFile->ulid))->assertNotFound();

    $order->updateQuietly(['customer_id' => $customer->id]);
});

test('iris invoice pdf is not served on another shop website', function () {
    $customer = createCustomer($this->shop);
    createInvoiceFor($customer, $this->shop, now()->toDateString(), 10);
    $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->first();
    $invoice->updateQuietly(['ulid' => (string) Str::ulid()]);

    $otherWebsite = createWebsite(createOwnShop('iris-invoice-other-shop')[2]);
    $otherWebsite->update(['status' => true]);

    $this->get('http://'.$otherWebsite->domain.'/invoice/'.$invoice->ulid)->assertNotFound();
    $this->get('http://'.$this->website->domain.'/invoice/'.$invoice->ulid)->assertOk();
});

function writeDropshippingDoc(string $slug, array $meta, string $body): string
{
    $directory = resource_path('markdown/'.BlogPosts::DROPSHIPPING_DOCS);
    @mkdir($directory, 0777, true);
    $frontMatter = collect(array_merge(['title' => $slug, 'summary' => 'Summary of '.$slug, 'date' => '2026-09-01'], $meta))
        ->map(fn ($value, $key) => $key.': '.$value)
        ->implode("\n");
    file_put_contents($directory.'/'.$slug.'.md', "---\n".$frontMatter."\n---\n".$body);

    return $directory.'/'.$slug.'.md';
}

function asDropshippingWebsite(Website $website, string $shopSlug, string $languageCode): Website
{
    $shop = $website->shop->replicate();
    $shop->type = ShopTypeEnum::DROPSHIPPING;
    $shop->slug = $shopSlug;
    $shop->setRelation('language', (new Language())->forceFill(['code' => $languageCode]));
    $website->setRelation('shop', $shop);

    return $website;
}

test('dropshipping docs are listed in the website language and only for their shops', function () {
    $files = [
        writeDropshippingDoc('zz-pest-connecting', ['category' => 'sales-channels', 'shops' => 'zz-pest-dse, zz-pest-awd'], 'See [the other guide](/docs/zz-pest-other).'),
        writeDropshippingDoc('zz-pest-connecting-es', ['category' => 'sales-channels', 'title' => 'Conectar', 'source_date' => '2026-09-01'], 'Hola'),
        writeDropshippingDoc('zz-pest-other', ['category' => 'products'], 'Other'),
        writeDropshippingDoc('zz-pest-other-es', ['category' => 'products', 'title' => 'Otra', 'source_date' => '2026-09-01'], 'Otra'),
        writeDropshippingDoc('zz-pest-uk-only', ['shops' => 'zz-pest-awd'], 'UK'),
    ];

    try {
        $website = asDropshippingWebsite($this->website, 'zz-pest-dse', 'es');

        $slugs = collect(ShowIrisDocs::make()->handle($website))->pluck('slug')->filter(fn (string $slug) => str_starts_with($slug, 'zz-pest-'))->values()->all();
        expect($slugs)->toEqualCanonicalizing(['zz-pest-connecting-es', 'zz-pest-other-es']);

        $page = ShowIrisDoc::make()->handle($website, 'zz-pest-connecting');
        expect($page['doc']['html'])->toContain('href="/docs/zz-pest-other-es"')
            ->and(collect($page['translations'])->pluck('lang')->all())->toBe(['en', 'es']);

        expect(fn () => ShowIrisDoc::make()->handle($website, 'zz-pest-uk-only'))->toThrow(NotFoundHttpException::class);
    } finally {
        array_map('unlink', $files);
    }
});

test('shopify docs explain that our products ship only from the aiku location and following our prices without changing yours', function () {
    $website = asDropshippingWebsite($this->website, 'dssk', 'en');

    expect(ShowIrisDoc::make()->handle($website, 'shopify-fulfilment-location')['doc']['html'])
        ->toContain('Your own stock of our products')
        ->toContain('ship only from the <b>aiku-</b> location')
        ->not->toContain('Ranked locations')
        ->and(ShowIrisDoc::make()->handle($website, 'product-descriptions-after-connecting')['doc']['html'])
        ->toContain('Following our prices without changing yours')
        ->toContain('Price managed in your Shopify');
});

test('shopify fulfilment location doc explains our AW Connect shipping profile and AW Standard rate, in every language', function (string $language, string $heading) {
    $website = asDropshippingWebsite($this->website, 'dssk', $language);

    $slug = $language === 'en' ? 'shopify-fulfilment-location' : "shopify-fulfilment-location-$language";

    expect(ShowIrisDoc::make()->handle($website, $slug)['doc']['html'])
        ->toContain($heading)
        ->toContain('AW Connect shipping (sho-ab12cd-3e)')
        ->toContain('AW Standard')
        ->toContain('Ships from')
        ->toContain('Shipping has moved to Markets');
})->with([
    ['en', 'Stores on shipping by market'],
    ['de', 'Shops mit Versand nach Märkten'],
    ['es', 'Tiendas con envío por mercados'],
    ['fr', 'Boutiques avec expédition par marché'],
    ['it', 'Negozi con spedizione per mercati'],
    ['nl', 'Winkels met verzending per markt'],
    ['pt', 'Lojas com envio por mercados'],
]);

test('exporting product data doc says available quantity counts packs, with 250 for on demand', function () {
    $website = asDropshippingWebsite($this->website, 'dssk', 'en');

    expect(ShowIrisDoc::make()->handle($website, 'exporting-product-data')['doc']['html'])
        ->toContain('counted in the packs you order')
        ->toContain('always show 250')
        ->not->toContain('the number of units in stock');
});

test('tiktok warehouse doc tells customers to keep their own warehouse, not to add ours', function () {
    $website = asDropshippingWebsite($this->website, 'dssk', 'en');
    $website->shop->company_name = 'Pest Warehouse Co';

    expect(ShowIrisDoc::make()->handle($website, 'tiktok-shop-warehouse')['doc']['html'])
        ->toContain('Use your own warehouse, not ours')
        ->toContain('Do not try to add our warehouse in TikTok')
        ->not->toContain('Pest Warehouse Co')
        ->and(ShowIrisDoc::make()->handle($website, 'connecting-tiktok-shop')['doc']['html'])
        ->toContain('a default warehouse with your own address')
        ->not->toContain('our warehouse address');
});

test('docs are not served on a website that is not dropshipping', function () {
    $this->get('http://'.$this->website->domain.'/docs')->assertNotFound();
});

test('a webpage can not take the docs address', function () {
    expect(fn () => StoreWebpage::make()->action($this->website, array_merge(Webpage::factory()->definition(), [
        'url'      => 'docs',
        'type'     => WebpageTypeEnum::CONTENT->value,
        'sub_type' => WebpageSubTypeEnum::CONTENT->value,
    ])))->toThrow(ValidationException::class);
});

test('dropshipping docs fill in the website own company, address and blocked countries', function () {
    $website = asDropshippingWebsite($this->website, 'zz-pest-dse', 'es');
    $website->shop->company_name = 'Pest & Co';
    $website->shop->banned_country_regions = [
        'GB' => ['billing' => false, 'delivery' => true, 'ip_block' => false, 'postcode' => null],
        'FR' => ['billing' => false, 'delivery' => true, 'ip_block' => false, 'postcode' => '/^20/'],
        'DE' => ['billing' => true, 'delivery' => false, 'ip_block' => false, 'postcode' => null],
    ];

    $html = ShowIrisDocs::make()->fillPlaceholders('{company_name}|{blocked_delivery_countries}', $website, isHtml: true);

    expect($html)->toContain('Pest &amp; Co|')
        ->toContain('<li>Reino Unido</li>')
        ->toContain('<li>Francia (')
        ->not->toContain('Alemania')
        ->and(ShowIrisDocs::make()->fillPlaceholders('{company_name}', $website))->toBe('Pest & Co');
});

test('saving a dropshipping shop queues a purge of its docs from the website cache', function () {
    config(['iris.cache.varnish' => true]);
    Queue::fake();

    PurgeIrisDocsFromVarnish::forShop($this->shop);
    PurgeIrisDocsFromVarnish::assertNotPushed();

    $website = asDropshippingWebsite($this->website, 'zz-pest-dse', 'es');
    $website->shop->setRelation('website', $website);
    PurgeIrisDocsFromVarnish::forShop($website->shop);
    PurgeIrisDocsFromVarnish::assertPushed();
});

test('iris pages still receive announcements, dialogs and appointment types on full loads and inertia visits', function () {
    [, $product] = createProduct($this->shop);
    $url         = 'http://'.$this->website->domain.'/catalogue/family/'.$product->family->slug;

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'             => 'Showroom visit '.Str::random(6),
        'meeting_mode'     => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes' => 60,
    ]);

    $middleware = new HandleIrisInertiaRequests();
    $expected   = [
        'appointment_types' => $middleware->getAppointmentTypes($this->website->fresh()),
        'announcements'     => \App\Actions\Web\Announcement\UI\GetIrisAnnouncements::run($this->website),
        'website_dialogs'   => \App\Actions\Web\WebsiteDialog\UI\GetIrisWebsiteDialogs::run($this->website),
    ];

    $response = $this->withoutVite()->get($url)->assertOk();
    preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $fullLoadProps = json_decode($matches[1], true)['props'];

    \Inertia\Inertia::flushShared();
    $inertiaProps = $this->withoutVite()
        ->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $middleware->version(request())])
        ->assertOk()
        ->json('props');

    $expectedIris = array_merge($middleware->getIrisData($this->website->fresh(), $fullLoadProps['iris']['locale']), [
        'google' => ['client_id' => config('services.google.client_id')],
    ]);

    expect(collect($expected['appointment_types'])->pluck('id'))->toContain($appointmentType->id)
        ->and($fullLoadProps['iris'])->toEqual(json_decode(json_encode($expectedIris), true))
        ->and($fullLoadProps['iris']['locale'])->toBe($this->shop->language->code)
        ->and($fullLoadProps['layout'])->toBe(['app_theme' => \Illuminate\Support\Arr::get($this->website->fresh()->published_layout, 'theme.color')])
        ->and($inertiaProps)->not->toHaveKey('iris');

    foreach ($expected as $prop => $value) {
        expect($fullLoadProps[$prop])->toEqual(json_decode(json_encode($value), true))
            ->and($inertiaProps[$prop])->toEqual(json_decode(json_encode($value), true));
    }
});

test('iris family page does not ship the family products twice', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    $response = $this->withoutVite()->get('http://'.$this->website->domain.'/catalogue/family/'.$family->slug);
    $response->assertOk();

    preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $page = json_decode($matches[1], true);

    expect($page['component'])->toBe('Catalogue/Family')
        ->and($page['props']['data']['family']['slug'])->toBe($family->slug)
        ->and($page['props']['data']['family'])->not->toHaveKey('products');
});

test('iris catalogue product page leaves out the rrp for visitors only (HELP-4214)', function () {
    [, $product] = createProduct($this->shop);
    $product->updateQuietly(['rrp' => 30]);
    $url = 'http://'.$this->website->domain.'/catalogue/products/'.$product->slug;

    $productProp = function () use ($url) {
        preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $this->withoutVite()->get($url)->assertOk()->getContent(), $matches);

        return json_decode($matches[1], true)['props']['data']['product'];
    };

    $visitorProduct = $productProp();
    actingAs(createWebUser(createCustomer($this->shop)), 'retina');
    $customerProduct = $productProp();

    expect($visitorProduct)->toHaveKey('price')
        ->not->toHaveKeys(['rrp', 'rrp_per_unit', 'margin', 'profit'])
        ->and($customerProduct['rrp'])->toEqual(30);
});

test('website lists open appointment types with their free times', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);

    $openType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Showroom visit '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $closedType = StoreAppointmentType::make()->action($this->shop, [
        'name'             => 'Closed '.Str::random(6),
        'meeting_mode'     => AppointmentTypeMeetingModeEnum::VIDEO_CALL->value,
        'duration_minutes' => 30,
        'is_active'        => false,
    ]);

    $response = $this->getJson('http://'.$this->website->domain.'/json/appointments')->assertOk();
    $types    = collect($response->json('appointment_types'));
    $listed   = $types->firstWhere('id', $openType->id);

    expect($response->json('shop_name'))->toBe($this->shop->name)
        ->and($response->json('country_code'))->toBe($this->shop->country?->code)
        ->and($listed)->not->toBeNull()
        ->and($listed['meeting_mode'])->toBe('store_visit')
        ->and($listed['slots'])->not->toBeEmpty()
        ->and($types->pluck('id'))->not->toContain($closedType->id);
});

test('iris layout lists the shop active appointment types for the help panel', function () {
    $sharesAppointments = function () {
        $request = Request::create('http://'.$this->website->domain.'/', 'GET');
        $request->merge(['website' => $this->website->fresh()]);

        return value((new HandleIrisInertiaRequests())->share($request)['appointment_types']);
    };

    $this->shop->appointmentTypes()->update(['is_active' => false]);
    expect($sharesAppointments())->toBe([]);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'             => 'Showroom visit '.Str::random(6),
        'meeting_mode'     => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes' => 60,
    ]);
    expect($sharesAppointments())->toBe([[
        'id'           => $appointmentType->id,
        'name'         => $appointmentType->name,
        'meeting_mode' => 'store_visit',
    ]]);
});

test('a website video call booking needs a WhatsApp number', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Video call '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::VIDEO_CALL->value,
        'duration_minutes'    => 30,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);
    $date  = array_key_last($slots);

    $payload = [
        'appointment_type_id' => $appointmentType->id,
        'date'                => $date,
        'time'                => $slots[$date][0],
        'contact_name'        => 'Video visitor',
        'email'               => 'video-'.Str::lower(Str::random(8)).'@example.com',
    ];

    $this->postJson('http://'.$this->website->domain.'/models/appointment', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'WhatsApp']);

    $this->postJson('http://'.$this->website->domain.'/models/appointment', [...$payload, 'phone' => '+421 900 123 456'])
        ->assertOk();
});

test('visitor books an appointment on the website and becomes a prospect', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Showroom visit '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'capacity_per_slot'   => 1,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);
    $date  = array_key_last($slots);
    $time  = $slots[$date][0];
    $email = 'web-visitor-'.Str::lower(Str::random(8)).'@example.com';

    $payload = [
        'appointment_type_id' => $appointmentType->id,
        'date'                => $date,
        'time'                => $time,
        'contact_name'        => 'Web visitor',
        'email'               => $email,
        'number_visitors'     => 3,
        'marketing_opt_in'    => true,
    ];

    $this->postJson('http://'.$this->website->domain.'/models/appointment', $payload)
        ->assertOk()
        ->assertJsonPath('starts_at', $date.' '.$time);

    $appointment = Appointment::where('appointment_type_id', $appointmentType->id)->sole();

    expect($appointment->source)->toBe(AppointmentSourceEnum::WEBSITE)
        ->and($appointment->state)->toBe(AppointmentStateEnum::REQUESTED)
        ->and($appointment->number_visitors)->toBe(3)
        ->and($appointment->visitor)->toBeInstanceOf(Prospect::class)
        ->and($appointment->visitor->email)->toBe($email)
        ->and($appointment->visitor->is_opt_in)->toBeTrue()
        ->and($appointment->visitor->dont_contact_me)->toBeFalse();

    $this->postJson('http://'.$this->website->domain.'/models/appointment', [...$payload, 'email' => 'other-'.$email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('time');

    $this->postJson('http://'.$this->website->domain.'/models/appointment', [...$payload, 'time' => $slots[$date][1], 'website_url' => 'http://spam.example'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website_url' => 'reload the page']);
});

test('a website booking is confirmed straight away when someone who arranges it is free', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);
    $staff = createShop($this->organisation)[1];

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Confirmed visit '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'capacity_per_slot'   => 1,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $appointmentType->attendees()->syncWithoutDetaching([$staff->id]);

    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);
    $date  = array_key_last($slots);

    Queue::fake();

    $this->postJson('http://'.$this->website->domain.'/models/appointment', [
        'appointment_type_id' => $appointmentType->id,
        'date'                => $date,
        'time'                => $slots[$date][0],
        'contact_name'        => 'Confirmed visitor',
        'email'               => 'confirmed-'.Str::lower(Str::random(8)).'@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('state', AppointmentStateEnum::ACCEPTED->value);

    $appointment = Appointment::where('appointment_type_id', $appointmentType->id)->sole();
    $queuedCodes = Queue::pushed(JobDecorator::class, fn ($job) => $job->displayName() === SendAppointmentEmail::class && $job->getParameters()[0] === $appointment->id)
        ->map(fn ($job) => $job->getParameters()[1])
        ->values()
        ->all();

    expect($appointment->state)->toBe(AppointmentStateEnum::ACCEPTED)
        ->and($appointment->user_id)->toBe($staff->id)
        ->and($appointment->accepted_at)->not->toBeNull()
        ->and($queuedCodes)->toBe([OutboxCodeEnum::APPOINTMENT_ACCEPTED->value]);
});

test('the appointment widget config is served only while the widget is on', function () {
    $key = EnableShopAppointmentWidget::make()->handle($this->shop)['key'];

    $openType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Widget visit '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);

    $response = $this->getJson('/app/api/appointments/widget-config?key='.$key)->assertOk();

    expect($response->json('data.shop_name'))->toBe($this->shop->name)
        ->and(collect($response->json('data.appointment_types'))->pluck('id'))->toContain($openType->id)
        ->and(AppointmentWidgetKey::make()->embedSnippet($key))->toContain('appointment-widget/v1.js?k='.$key)
        ->and(AppointmentWidgetKey::make()->embedSnippet($key))->toContain('data-aiku-appointments');

    EnableShopAppointmentWidget::make()->handle($this->shop->fresh(), false);

    $this->getJson('/app/api/appointments/widget-config?key='.$key)->assertNotFound();
    $this->getJson('/app/api/appointments/widget-config?key=doesnotexist')->assertNotFound();
    $this->getJson('/app/api/appointments/widget-config')->assertNotFound();

    expect(EnableShopAppointmentWidget::make()->handle($this->shop->fresh())['key'])->toBe($key);
});

test('a visitor on another website books an appointment through the widget', function () {
    $key = EnableShopAppointmentWidget::make()->handle($this->shop)['key'];

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Widget showroom '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'capacity_per_slot'   => 1,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);
    $date  = array_key_last($slots);
    $time  = $slots[$date][0];

    $payload = [
        'appointment_type_id' => $appointmentType->id,
        'date'                => $date,
        'time'                => $time,
        'contact_name'        => 'Widget visitor',
        'email'               => 'widget-visitor-'.Str::lower(Str::random(8)).'@example.com',
    ];

    $this->postJson('/app/api/appointments/widget-booking?key=doesnotexist', $payload)->assertNotFound();

    $this->postJson('/app/api/appointments/widget-booking?key='.$key, $payload)
        ->assertOk()
        ->assertJsonPath('starts_at', $date.' '.$time);

    $appointment = Appointment::where('appointment_type_id', $appointmentType->id)->sole();

    expect($appointment->shop_id)->toBe($this->shop->id)
        ->and($appointment->state)->toBe(AppointmentStateEnum::REQUESTED);

    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::run($this->organisation, \App\Models\Catalogue\Shop::factory()->make()->toArray());
    $otherKey  = EnableShopAppointmentWidget::make()->handle($otherShop)['key'];

    $this->postJson('/app/api/appointments/widget-booking?key='.$otherKey, [...$payload, 'time' => $slots[$date][1] ?? $time])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('appointment_type_id');
});

test('a visitor moves and cancels their appointment from the link in their email', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);
    $this->withoutMiddleware(ThrottleRequests::class);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'                => 'Managed visit '.Str::random(6),
        'meeting_mode'        => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'duration_minutes'    => 60,
        'min_notice_hours'    => 0,
        'booking_window_days' => 14,
        'capacity_per_slot'   => 1,
        'availability'        => ['weekly' => array_fill_keys(range(1, 7), [['from' => '09:00', 'to' => '17:00']])],
    ]);
    $slots = GetAppointmentTypeAvailableSlots::run($appointmentType);
    $date  = array_key_last($slots);
    [$firstTime, $secondTime] = $slots[$date];

    $appointment = StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $appointmentType->id,
        'starts_at'           => $date.' '.$firstTime,
        'contact_name'        => 'Managing visitor',
        'email'               => 'managing-'.Str::lower(Str::random(8)).'@example.com',
        'state'               => AppointmentStateEnum::REQUESTED->value,
    ]);

    $link   = '/appointment/'.$appointment->id.'/'.$appointment->manageToken();
    $site   = 'http://'.$this->website->domain;
    $models = $site.'/models'.$link;
    $page   = ShowIrisAppointment::make()->handle($appointment);

    expect($appointment->manageUrl())->toBe('https://'.$this->website->domain.$link)
        ->and(SendAppointmentEmail::make()->generateBodyHtml($appointment, OutboxCodeEnum::APPOINTMENT_REQUESTED))->toContain($link)
        ->and(SendAppointmentEmail::make()->generateBodyHtml($appointment, OutboxCodeEnum::APPOINTMENT_CANCELLED))->not->toContain($link)
        ->and($page['appointment']['can_change'])->toBeTrue()
        ->and($page['appointment']['time'])->toBe($firstTime)
        ->and($page['free_slots'][$date])->toContain($secondTime)
        ->and($page['free_slots'][$date])->not->toContain($firstTime);

    $this->get($site.'/appointment/'.$appointment->id.'/not-the-token')->assertNotFound();
    $this->postJson($site.'/models/appointment/'.$appointment->id.'/not-the-token/cancel')->assertNotFound();

    $this->postJson($models.'/reschedule', ['date' => $date, 'time' => $secondTime])
        ->assertOk()
        ->assertJsonPath('time', $secondTime)
        ->assertJsonPath('state', AppointmentStateEnum::REQUESTED->value);

    $timezone = $this->shop->timezone?->name ?? 'UTC';
    expect($appointment->refresh()->starts_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'))->toBe($date.' '.$secondTime)
        ->and(GetAppointmentTypeAvailableSlots::make()->isAvailable($appointmentType, $date, $firstTime))->toBeTrue();

    $this->postJson($models.'/reschedule', ['date' => $date, 'time' => $secondTime])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('time');

    $this->postJson($models.'/cancel', ['reason' => 'Plans changed'])
        ->assertOk()
        ->assertJsonPath('state', AppointmentStateEnum::CANCELLED->value)
        ->assertJsonPath('can_change', false);

    expect($appointment->refresh()->state)->toBe(AppointmentStateEnum::CANCELLED)
        ->and($appointment->state_reason)->toContain('Plans changed')
        ->and(GetAppointmentTypeAvailableSlots::make()->isAvailable($appointmentType, $date, $secondTime))->toBeTrue();

    $this->postJson($models.'/cancel')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');
});

test('data feeds are rebuilt hourly only while someone reads them', function () {
    Storage::fake('data-feeds');
    $this->shop->update(['is_aiku' => true, 'state' => ShopStateEnum::OPEN]);
    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $path = SaveDataFeeds::path($this->shop);
    Cache::forget(SaveDataFeeds::readCacheKey($this->shop));

    $this->get('http://'.$this->website->domain.'/data-feed.csv')->assertOk();

    expect(Storage::disk('data-feeds')->allFiles('shop'))->toBe([$path]);

    Storage::disk('data-feeds')->put($path, 'stale');
    $this->artisan('data_feeds:save')->assertSuccessful();

    expect(Storage::disk('data-feeds')->get($path))->toContain('Product code')
        ->and(Storage::disk('data-feeds')->allFiles('shop'))->toBe([$path]);

    Cache::forget(SaveDataFeeds::readCacheKey($this->shop));
    $this->artisan('data_feeds:save')->assertSuccessful();

    Storage::disk('data-feeds')->assertMissing($path);
});

test('a customer rates their order and its products from the stars in the review reminder email', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();

    [, $product] = createProduct($this->shop);
    $order       = createOrder(createCustomer($this->shop), $product);
    $page        = ShowIrisOrderReview::make()->handle($order);
    $productId   = $page['products'][0]['reviewable_id'];

    $link  = '/review/'.$order->id.'/'.$order->reviewToken();
    $site  = 'http://'.$this->website->domain;
    $email = (new ProcessReviewReminderRecipients())->generateReviewLinks((string) $order->id);

    expect($order->reviewUrl())->toBe('https://'.$this->website->domain.$link)
        ->and($email)->toContain(e($order->reviewUrl(['rating' => 5])))
        ->and($email)->toContain(e($order->reviewUrl(['product' => $productId, 'rating' => 1])))
        ->and($page['company']['reviewable_id'])->toBe($order->id);

    $this->get($site.'/review/'.$order->id.'/not-the-token')->assertNotFound();
    $this->postJson($site.'/models/review/'.$order->id.'/not-the-token', [
        'reviews' => [['scope' => 'order', 'reviewable_id' => $order->id, 'rating' => 5]],
    ])->assertNotFound();

    $this->postJson($site.'/models'.$link, [
        'reviews' => [
            ['scope' => 'order', 'reviewable_id' => $order->id, 'rating' => 5, 'message' => 'Fast delivery'],
            ['scope' => 'product', 'reviewable_id' => $productId, 'rating' => 4],
        ],
    ])->assertOk()
        ->assertJsonPath('company.rating', 5)
        ->assertJsonPath('company.message', 'Fast delivery')
        ->assertJsonPath('products.0.rating', 4);

    $orderReviews = fn () => Review::query()->where('order_id', $order->id);
    $numberReviews = $orderReviews()->count();

    expect($orderReviews()->where('scope', ReviewScopeEnum::ORDER)->value('customer_id'))->toBe($order->customer_id);

    $this->postJson($site.'/models'.$link, [
        'reviews' => [['scope' => 'product', 'reviewable_id' => $productId, 'rating' => 2, 'message' => 'Arrived broken']],
    ])->assertOk()->assertJsonPath('products.0.rating', 2);

    expect($orderReviews()->count())->toBe($numberReviews)
        ->and((int) $orderReviews()->where('product_id', $productId)->value('rating_main'))->toBe(2);

    $this->postJson($site.'/models'.$link, [
        'reviews' => [['scope' => 'product', 'reviewable_id' => 999999999, 'rating' => 5]],
    ])->assertUnprocessable()->assertJsonValidationErrors('reviews.0.reviewable_id');
});

test('a test review reminder links to a preview of the review page that saves nothing', function () {
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($this->website->domain);
    Queue::fake();

    [, $product] = createProduct($this->shop);
    $product->updateQuietly(['status' => ProductStatusEnum::FOR_SALE]);
    $outbox = $this->shop->outboxes()->where('code', OutboxCodeEnum::REVIEW_REMINDER)->firstOrFail();

    $links = GetOutboxTestEmailData::run($outbox)['review_reminder_links'];
    preg_match('#https://'.preg_quote($this->website->domain, '#').'(/review/preview/[^"]+)#', html_entity_decode($links), $match);
    parse_str((string) parse_url($match[1], PHP_URL_QUERY), $query);
    $site = 'http://'.$this->website->domain;

    expect($links)->toContain('/review/preview/')
        ->and(explode(',', $query['products']))->toContain((string) $product->id)
        ->and($links)->not->toContain('/review/'.$product->id.'/');

    $numberReviews = Review::query()->count();

    $this->get($site.$match[1])
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('OrderReview', false)
            ->where('isPreview', true)
            ->where('order.reference', $query['reference'])
            ->where('order.products', fn ($products) => collect($products)->pluck('reviewable_id')->contains($product->id)));

    $tamperedQuery = http_build_query([...$query, 'products' => $query['products'].',999999999']);
    $previewPath   = strtok($match[1], '?');

    $this->get($site.$previewPath.'?'.$tamperedQuery)->assertNotFound();
    $this->get($site.'/review/preview/not-the-token?'.http_build_query($query))->assertNotFound();

    expect(ShowIrisOrderReviewPreview::token($this->shop, $query['reference'], $query['products']))->toBe(basename($previewPath))
        ->and(Review::query()->count())->toBe($numberReviews);
});

test('the hourly data feed run checks every read mark of a shop at once and rebuilds the same feeds', function () {
    Storage::fake('data-feeds');
    $this->shop->update(['is_aiku' => true, 'state' => ShopStateEnum::OPEN]);

    [, $product] = createProduct($this->shop);
    $product->update(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'exclusive_for_customer_id' => null, 'is_for_sale' => true]);
    $cartonProduct = $product->replicate(['slug']);
    $cartonCode = $product->code.'-36u-'.uniqid();
    $cartonProduct->forceFill(['code' => $cartonCode, 'slug' => strtolower($cartonCode), 'is_for_sale' => false])->saveQuietly();
    $department = $product->department;
    $family     = $product->family;
    $department->update(['state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE]);
    $family->update(['state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE]);
    $collection = \App\Actions\Catalogue\Collection\StoreCollection::make()->action($this->shop, ['code' => 'feed-'.uniqid(), 'name' => 'Feed collection']);
    $collection->update(['state' => \App\Enums\Catalogue\Collection\CollectionStateEnum::ACTIVE]);
    DB::table('collection_has_models')->insert(['collection_id' => $collection->id, 'model_type' => 'Product', 'model_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('collection_has_models')->insert(['collection_id' => $collection->id, 'model_type' => 'Product', 'model_id' => $cartonProduct->id, 'created_at' => now(), 'updated_at' => now()]);

    $read   = [$this->shop, $department, $collection];
    $unread = [$family];
    foreach ($read as $parent) {
        SaveDataFeeds::markAsRead($parent);
    }
    foreach ([...$read, ...$unread] as $parent) {
        if (in_array($parent, $unread, true)) {
            Cache::forget(SaveDataFeeds::readCacheKey($parent));
        }
        Storage::disk('data-feeds')->put(SaveDataFeeds::path($parent), 'stale');
    }

    $this->artisan('data_feeds:save')->assertSuccessful();

    foreach ($read as $parent) {
        expect(Storage::disk('data-feeds')->get(SaveDataFeeds::path($parent)))->toContain('Product code')->toContain($product->code)->not->toContain($cartonProduct->code);
    }
    Storage::disk('data-feeds')->assertMissing(SaveDataFeeds::path($family));

    $export     = new \App\Exports\Marketing\ProductsInShopExport($this->shop);
    $fullRows   = $export->query()->select('products.*', 'families.name as family_name', 'families.code as family_code', 'departments.name as department_name', 'departments.code as department_code', 'sub_departments.name as subdepartment_name', 'sub_departments.code as subdepartment_code')->get();
    $feedRows   = iterator_to_array($export->generator(), false);

    expect(array_map(fn ($row) => $export->map($row), $feedRows))->toBe($fullRows->map(fn ($row) => $export->map($row))->all())
        ->and(count($feedRows))->toBeGreaterThan(0);
});

test('iris website data links its live terms page, the licence Google Images reads for product images (HELP-4262)', function () {
    $termsWebpage = $this->website->webpages()->where('sub_type', WebpageSubTypeEnum::TERMS_AND_CONDITIONS)->firstOrFail();

    $termsWebpage->update(['state' => WebpageStateEnum::CLOSED]);
    expect(WebsiteIrisResource::make($this->website)->getArray()['terms_url'])->toBeNull();

    $termsWebpage->update(['state' => WebpageStateEnum::LIVE]);
    expect(WebsiteIrisResource::make($this->website)->getArray()['terms_url'])->toBe($termsWebpage->fresh()->getCanonicalUrl())
        ->and($termsWebpage->fresh()->getCanonicalUrl())->toEndWith('terms-and-conditions');
});

test('favicon and apple touch icons redirect to the website icon without a session cookie, so the edge can keep them an hour', function () {
    $favicons = ['48' => 'https://media.test/icon-48.png', '180' => 'https://media.test/icon-180.png'];

    foreach ([
        'favicon.ico'                           => ['iris.iris_favicon', '48'],
        'apple-touch-icon.png'                  => ['iris.iris_apple_touch_icon', '180'],
        'apple-touch-icon-precomposed.png'      => ['iris.iris_apple_touch_icon', '180'],
        'apple-touch-icon-120x120-precomposed.png' => ['iris.iris_apple_touch_icon', '180'],
    ] as $path => [$routeName, $size]) {
        $request = ActionRequest::create("https://www.shop.test/$path");
        $request->merge(['favicons' => $favicons]);

        $response = ShowIrisFavicon::make()->asController($request);

        expect(app('router')->getRoutes()->match($request)->getName())->toBe($routeName)
            ->and(app('router')->getRoutes()->match($request)->excludedMiddleware())->toBe([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
            ->and($response->getTargetUrl())->toBe($favicons[$size])
            ->and($response->headers->get('Cache-Control'))->toBe('max-age=3600, public');
    }

    expect(app('router')->getRoutes()->match(Request::create('https://www.shop.test/apple-touch-icon-foo.png'))->getName())->toBe('iris.')
        ->and(app('router')->getRoutes()->match(Request::create('https://www.shop.test/logo.png'))->getName())->toBe('iris.');
});

function enableShopLanguages(\App\Models\Catalogue\Shop $shop, ?array $codes): void
{
    $settings = $shop->settings ?? [];
    if ($codes === null) {
        unset($settings['enabled_languages']);
    } else {
        $settings['enabled_languages'] = array_values(Language::whereIn('code', $codes)->pluck('id')->all());
    }
    $shop->settings = $settings;
    $shop->saveQuietly();
}

function otherLanguageCodes(\App\Models\Catalogue\Shop $shop): array
{
    return Language::whereIn('code', ['fr', 'de', 'es', 'en'])->where('id', '!=', $shop->language_id)->orderBy('code')->limit(2)->pluck('code')->all();
}

function runWebsiteLocale(Website $website, string $uri, string $method = 'GET')
{
    $request = Request::create('http://'.$website->domain.$uri, $method);
    $request->server->set('QUERY_STRING', (string) parse_url($uri, PHP_URL_QUERY));
    $request->merge(['website' => $website->fresh(), 'locale' => $website->shop->language->code]);

    return (new \App\Http\Middleware\SetWebsiteLocale())->handle($request, fn () => response('ok'));
}

function runWebUserLocale(Website $website, array $cookies = [], string $uri = '/app/dashboard')
{
    $request = Request::create('http://'.$website->domain.$uri, 'GET', [], $cookies);
    $request->merge(['website' => $website->fresh()]);

    return (new \App\Http\Middleware\SetWebUserLocale())->handle($request, fn () => response('ok'));
}

test('website locale without the setting stays the shop language and a not enabled locale redirects to the untouched rest of the url', function () {
    enableShopLanguages($this->shop, null);
    $shopCode = $this->shop->language->code;
    [$otherCode] = otherLanguageCodes($this->shop);

    expect(runWebsiteLocale($this->website, '/about')->getContent())->toBe('ok')
        ->and(app()->getLocale())->toBe($shopCode)
        ->and(runWebsiteLocale($this->website, '/about?locale='.$shopCode)->getContent())->toBe('ok')
        ->and(app()->getLocale())->toBe($shopCode)
        ->and(runWebsiteLocale($this->website, '/about?locale='.$otherCode, 'POST')->getContent())->toBe('ok')
        ->and(app()->getLocale())->toBe($shopCode);

    foreach ([$otherCode, 'zz', '', 'x%5B%5D'] as $notEnabled) {
        $response = runWebsiteLocale($this->website, '/about?page=2&a.b=1&x=1&x=2&flag&locale='.$notEnabled);
        expect($response->isRedirect('http://'.$this->website->domain.'/about?page=2&a.b=1&x=1&x=2&flag'))->toBeTrue()
            ->and($response->headers->get('X-Aiku-Cacheable-Redirect'))->toBe('1');
    }

    expect(runWebsiteLocale($this->website, '/about?locale[]=fr')->isRedirect('http://'.$this->website->domain.'/about'))->toBeTrue();
});

test('website locale accepts a language enabled for the shop', function () {
    [$enabledCode, $notEnabledCode] = otherLanguageCodes($this->shop);
    enableShopLanguages($this->shop, [$enabledCode]);

    expect(runWebsiteLocale($this->website, '/about?locale='.$enabledCode)->getContent())->toBe('ok')
        ->and(app()->getLocale())->toBe($enabledCode)
        ->and(runWebsiteLocale($this->website, '/about?locale='.$notEnabledCode)->isRedirect())->toBeTrue();

    enableShopLanguages($this->shop, null);
});

test('each website language keeps its own cached page and breaking a webpage clears every language', function () {
    [$enabledCode] = otherLanguageCodes($this->shop);
    enableShopLanguages($this->shop, [$enabledCode]);
    $shopCode = $this->shop->language->code;
    $webpage  = $this->website->webpages()->firstOrFail();
    $website  = $this->website->fresh();

    $shopKey  = \App\Actions\Web\Webpage\Iris\ShowIrisWebpage::webpageCacheKey($website, $webpage->id, false, $shopCode);
    $otherKey = \App\Actions\Web\Webpage\Iris\ShowIrisWebpage::webpageCacheKey($website, $webpage->id, false, $enabledCode);

    expect($shopKey)->toBe(config('iris.cache.webpage.prefix').'_'.$website->id.'_out_'.$webpage->id)
        ->and($otherKey)->toBe($shopKey.'_'.$enabledCode);

    app()->setLocale($enabledCode);
    expect(\App\Actions\Web\Webpage\Iris\ShowIrisWebpage::webpageCacheKey($website, $webpage->id, false))->toBe($otherKey);
    app()->setLocale($shopCode);

    foreach ([true, false] as $loggedIn) {
        foreach ([$shopCode, $enabledCode] as $code) {
            Cache::put(\App\Actions\Web\Webpage\Iris\ShowIrisWebpage::webpageCacheKey($website, $webpage->id, $loggedIn, $code), 'cached');
        }
    }

    \App\Actions\Web\Webpage\Iris\ShowIrisWebpage::forgetWebpageCache($webpage->fresh());

    foreach ([true, false] as $loggedIn) {
        foreach ([$shopCode, $enabledCode] as $code) {
            expect(Cache::has(\App\Actions\Web\Webpage\Iris\ShowIrisWebpage::webpageCacheKey($website, $webpage->id, $loggedIn, $code)))->toBeFalse();
        }
    }

    enableShopLanguages($this->shop, null);
});

test('web user locale keeps the old behaviour until the list is saved, then ignores languages not enabled', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    $shopCode = $this->shop->language->code;
    enableShopLanguages($this->shop, null);

    runWebUserLocale($this->website, ['aiku_guest_locale' => $otherCode]);
    expect(app()->getLocale())->toBe($otherCode);

    runWebUserLocale($this->website, [], '/app/login?locale='.$otherCode);
    expect(app()->getLocale())->toBe($shopCode);

    $webUser = createWebUser(createCustomer($this->shop));
    $webUser->update(['language_id' => Language::where('code', $otherCode)->value('id')]);
    actingAs($webUser, 'retina');

    runWebUserLocale($this->website);
    expect(app()->getLocale())->toBe($otherCode);

    enableShopLanguages($this->shop, [$shopCode]);
    runWebUserLocale($this->website);
    expect(app()->getLocale())->toBe($shopCode);

    enableShopLanguages($this->shop, [$otherCode]);
    runWebUserLocale($this->website);
    expect(app()->getLocale())->toBe($otherCode);

    auth('retina')->logout();
    runWebUserLocale($this->website, [], '/app/login?locale='.$otherCode);
    expect(app()->getLocale())->toBe($otherCode);

    enableShopLanguages($this->shop, null);
});

test('language switch saves the web user language, only enabled ones once the list is saved', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    $otherLanguageId = Language::where('code', $otherCode)->value('id');
    $webUser = createWebUser(createCustomer($this->shop));
    $updateLanguage = fn () => \App\Actions\Retina\UI\Profile\UpdateRetinaProfile::make()->action($webUser->fresh(), ['language_id' => $otherLanguageId]);

    enableShopLanguages($this->shop, null);
    $webUser->update(['language_id' => $this->shop->language_id]);
    $updateLanguage();
    expect($webUser->fresh()->language_id)->toBe($otherLanguageId);

    enableShopLanguages($this->shop, [$this->shop->language->code]);
    $webUser->update(['language_id' => $this->shop->language_id]);
    expect($updateLanguage)->toThrow(ValidationException::class);

    enableShopLanguages($this->shop, [$otherCode]);
    $updateLanguage();
    expect($webUser->fresh()->language_id)->toBe($otherLanguageId);

    enableShopLanguages($this->shop, null);
});

test('a website visitor in another language gets the translated product and category texts, field by field, and nobody else does', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    DB::table('products')->where('id', $product->id)->update([
        'name'           => 'Lavender oil',
        'description'    => 'Shop description',
        'name_i8n'       => json_encode([$otherCode => 'Lavendelöl']),
        'description_i8n' => json_encode([$otherCode => '<p> </p>']),
    ]);
    DB::table('product_categories')->where('id', $family->id)->update([
        'name'     => 'Essential oils',
        'name_i8n' => json_encode([$otherCode => 'Ätherische Öle']),
    ]);
    $product = $product->fresh();
    $family  = $family->fresh();

    $productName = fn () => DB::table('products')->where('id', $product->id)->select(\App\Helpers\WebsiteLanguage::column('products.name'))->value('name');

    expect(\App\Helpers\WebsiteLanguage::translateTo())->toBeNull()
        ->and(\App\Helpers\WebsiteLanguage::text($product, 'name'))->toBe('Lavender oil')
        ->and($productName())->toBe('Lavender oil')
        ->and(\App\Actions\Iris\Json\GetIrisSidebarData::cacheKey($this->website->id, null))->toBe('irisData:website:'.$this->website->id.':sideBar');

    \App\Helpers\WebsiteLanguage::set($this->shop, $this->shop->language->code);
    expect(\App\Helpers\WebsiteLanguage::translateTo())->toBeNull();

    \App\Helpers\WebsiteLanguage::set($this->shop, $otherCode);
    $resource = \App\Http\Resources\Web\WebBlockProductResource::make($product)->toArray(request());

    expect(\App\Helpers\WebsiteLanguage::translateTo())->toBe($otherCode)
        ->and(\App\Helpers\WebsiteLanguage::text($product, 'name'))->toBe('Lavendelöl')
        ->and(\App\Helpers\WebsiteLanguage::text($product, 'description'))->toBe('Shop description')
        ->and(\App\Helpers\WebsiteLanguage::text($family, 'name'))->toBe('Ätherische Öle')
        ->and($resource['name'])->toBe('Lavendelöl')
        ->and($resource['description'])->toBe('Shop description')
        ->and($productName())->toBe('Lavendelöl')
        ->and(\App\Actions\Iris\Json\GetIrisSidebarData::cacheKey($this->website->id, $otherCode))->toBe('irisData:website:'.$this->website->id.':sideBar:'.$otherCode);

    Cache::put('iris:last_ordered_products:'.$family->id.':none', collect(['english names']));
    expect(\App\Actions\Catalogue\Product\Json\GetIrisLastOrderedProducts::make()->handle($family, [])->all())->not->toBe(['english names']);
    Cache::forget('iris:last_ordered_products:'.$family->id.':none');
    Cache::forget('iris:last_ordered_products:'.$family->id.':none:'.$otherCode);

    $webpage = new Webpage(['title' => 'Lavender oil', 'breadcrumb_label' => 'Our lavender']);
    $webpage->setRelation('model', $product);
    expect(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'title'))->toBe('Lavendelöl')
        ->and(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'breadcrumb_label'))->toBe('Our lavender');

    request()->attributes->remove('website_translation_locale');
    expect(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'title'))->toBe('Lavender oil');
});

test('preview languages work in the website address and for staff customers, never in the language switch or for other customers', function () {
    Http::fake();
    [$previewCode, $otherCode] = otherLanguageCodes($this->shop);
    $previewLanguageId = Language::where('code', $previewCode)->value('id');
    enableShopLanguages($this->shop, [$this->shop->language->code]);

    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['preview_languages' => [$previewLanguageId]]);

    expect($shop->settings['preview_languages'])->toBe([$previewLanguageId])
        ->and($shop->previewLanguageCodes())->toBe([$previewCode])
        ->and($shop->enablesLanguage($previewCode))->toBeFalse()
        ->and($shop->showsWebsiteIn($previewCode))->toBeTrue()
        ->and($shop->showsWebsiteIn($otherCode))->toBeFalse();

    expect(runWebsiteLocale($this->website, '/about?locale='.$previewCode)->getContent())->toBe('ok')
        ->and(app()->getLocale())->toBe($previewCode)
        ->and(runWebsiteLocale($this->website, '/about?locale='.$otherCode)->isRedirect())->toBeTrue();

    $irisData = (new HandleIrisInertiaRequests())->getIrisData($this->website->fresh(), $previewCode);
    expect(collect($irisData['website_i18n']['enabled_languages'])->pluck('code')->all())->not->toContain($previewCode);

    $customer = createCustomer($shop);
    $webUser  = createWebUser($customer);
    $choosePreview = fn () => \App\Actions\Retina\UI\Profile\UpdateRetinaProfile::make()->action($webUser->fresh(), ['language_id' => $previewLanguageId]);

    $customer->updateQuietly(['is_staff' => false]);
    expect($choosePreview)->toThrow(ValidationException::class)
        ->and($shop->languageIdsFor($customer->fresh()))->not->toContain($previewLanguageId);

    $customer->updateQuietly(['is_staff' => true]);
    $choosePreview();
    expect($webUser->fresh()->language_id)->toBe($previewLanguageId)
        ->and($shop->languageIdsFor($customer->fresh()))->toContain($previewLanguageId);

    actingAs($webUser->fresh(), 'retina');
    runWebUserLocale($this->website);
    expect(app()->getLocale())->toBe($previewCode);

    $customer->updateQuietly(['is_staff' => false]);
    actingAs($webUser->fresh()->load('customer'), 'retina');
    runWebUserLocale($this->website);
    expect(app()->getLocale())->toBe($shop->language->code);

    auth('retina')->logout();
    $settings = $shop->fresh()->settings;
    unset($settings['preview_languages']);
    $shop->settings = $settings;
    $shop->saveQuietly();
    enableShopLanguages($this->shop, null);
});

test('enabled languages shop setting is saved, always keeps the main language, is audited and reaches the iris data', function () {
    Http::fake();
    [$otherCode] = otherLanguageCodes($this->shop);
    $otherLanguageId = Language::where('code', $otherCode)->value('id');
    enableShopLanguages($this->shop, null);
    $this->shop->refresh();

    expect($this->shop->enabledLanguageIds())->toBe([$this->shop->language_id])
        ->and($this->shop->restrictsLanguages())->toBeFalse();

    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop, ['enabled_languages' => [$otherLanguageId]]);

    expect($shop->settings['enabled_languages'])->toBe([$otherLanguageId])
        ->and($shop->enabledLanguageIds())->toBe([$shop->language_id, $otherLanguageId])
        ->and($shop->audits()->latest('id')->first()->new_values)->toMatchArray(['settings.enabled_languages' => [$otherLanguageId]]);

    $irisData = (new HandleIrisInertiaRequests())->getIrisData($this->website->fresh(), $shop->language->code);
    expect(collect($irisData['website_i18n']['enabled_languages'])->pluck('code')->all())->toContain($otherCode, $shop->language->code);

    expect(fn () => \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['enabled_languages' => [Language::where('status', false)->value('id')]]))
        ->toThrow(ValidationException::class);

    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['enabled_languages' => []]);
    expect($shop->enabledLanguageIds())->toBe([$shop->language_id])
        ->and($shop->restrictsLanguages())->toBeFalse();
});

function displayCurrencyCodesFor(\App\Models\Catalogue\Shop $shop): array
{
    $codes = array_values(array_diff(['CZK', 'HUF', 'PLN'], [$shop->currency->code]));
    foreach ($codes as $code) {
        $currency = \App\Models\Helpers\Currency::where('code', $code)->firstOrFail();
        \App\Models\Helpers\CurrencyExchange::firstOrCreate(['currency_id' => $currency->id, 'date' => now()->toDateString()], ['exchange' => 1, 'source' => 'test']);
    }

    return $codes;
}

function setShopDisplayCurrencies(\App\Models\Catalogue\Shop $shop, ?array $codes): void
{
    $settings = $shop->settings ?? [];
    if ($codes === null) {
        unset($settings['display_currencies']);
    } else {
        $settings['display_currencies'] = $codes;
    }
    $shop->settings = $settings;
    $shop->saveQuietly();
}

function asDropshippingShop(\App\Models\Catalogue\Shop $shop, bool $isDropshipping = true): void
{
    static $originalTypes = [];
    $originalTypes[$shop->id] ??= $shop->fresh()->type;
    $shop->type = $isDropshipping ? ShopTypeEnum::DROPSHIPPING : $originalTypes[$shop->id];
    $shop->saveQuietly();
}

test('display currencies: by default a shop offers only its own currency and a customer cannot choose another', function () {
    asDropshippingShop($this->shop);
    setShopDisplayCurrencies($this->shop, null);
    $shop = $this->shop->fresh();
    [$otherCode] = displayCurrencyCodesFor($shop);
    $webUser = createWebUser(createCustomer($shop));

    expect($shop->displayCurrencyCodes())->toBe([$shop->currency->code])
        ->and($shop->displayCurrencyRates())->toBe([]);

    expect(fn () => \App\Actions\Retina\UI\Profile\UpdateRetinaProfile::make()->action($webUser->fresh(), ['display_currency' => $otherCode]))
        ->toThrow(ValidationException::class);

    asDropshippingShop($this->shop, false);
});

test('display currencies shop setting is saved without the shop currency, is audited and only takes currencies with an exchange rate', function () {
    Http::fake();
    setShopDisplayCurrencies($this->shop, null);
    [$czk] = displayCurrencyCodesFor($this->shop);
    $shopCode = $this->shop->currency->code;

    asDropshippingShop($this->shop, false);
    expect(fn () => \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['display_currencies' => [$czk]]))
        ->toThrow(ValidationException::class);

    asDropshippingShop($this->shop);
    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['display_currencies' => [$czk, $shopCode]]);

    expect($shop->settings['display_currencies'])->toBe([$czk])
        ->and($shop->displayCurrencyCodes())->toBe([$shopCode, $czk])
        ->and($shop->audits()->latest('id')->first()->new_values)->toMatchArray(['settings.display_currencies' => [$czk]]);

    $currencyWithoutRate = \App\Models\Helpers\Currency::whereNotIn('id', \App\Models\Helpers\CurrencyExchange::query()->select('currency_id'))
        ->where('code', '!=', config('app.currency_exchange.pivot'))->value('code');
    expect(fn () => \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['display_currencies' => [$currencyWithoutRate]]))
        ->toThrow(ValidationException::class);

    $zeroDecimalCurrency = \App\Models\Helpers\Currency::where('fraction_digits', 0)->whereNotIn('code', \App\Models\Catalogue\Shop::DISPLAY_CURRENCY_WHOLE_NUMBERS)->first();
    if ($zeroDecimalCurrency) {
        \App\Models\Helpers\CurrencyExchange::firstOrCreate(['currency_id' => $zeroDecimalCurrency->id, 'date' => now()->toDateString()], ['exchange' => 1, 'source' => 'test']);
        expect(\App\Models\Catalogue\Shop::currencyCodesWithExchangeRate())->not->toContain($zeroDecimalCurrency->code);
    }

    $shop = \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['display_currencies' => []]);
    expect($shop->displayCurrencyCodes())->toBe([$shopCode]);

    asDropshippingShop($this->shop, false);
});

test('display currency rates come from the daily exchange with whole numbers for CZK and HUF, without calling a rate provider', function () {
    Http::fake();
    $codes = displayCurrencyCodesFor($this->shop);
    $shopCode = $this->shop->currency->code;
    $rates = ['CZK' => 25.31, 'HUF' => 391.7, 'PLN' => 4.2834];
    foreach ($codes as $code) {
        Cache::put('current-currency-exchange:'.$shopCode.'-'.$code, $rates[$code], 60);
    }
    setShopDisplayCurrencies($this->shop, $codes);

    $displayCurrencies = collect($this->shop->fresh()->displayCurrencyRates())->keyBy('code');

    expect($displayCurrencies->keys()->sort()->values()->all())->toBe(collect([...$codes, $shopCode])->sort()->values()->all())
        ->and($displayCurrencies[$shopCode]['rate'])->toBe(1.0);
    foreach ($codes as $code) {
        expect($displayCurrencies[$code]['rate'])->toBe($rates[$code])
            ->and($displayCurrencies[$code]['fraction_digits'])->toBe($code === 'PLN' ? 2 : 0);
    }
    Http::assertNothingSent();

    setShopDisplayCurrencies($this->shop, null);
});

test('a customer display currency is saved on the web user, limited to the shop list, and orders stay in the shop currency', function () {
    [$czk, $huf] = displayCurrencyCodesFor($this->shop);
    asDropshippingShop($this->shop);
    setShopDisplayCurrencies($this->shop, [$czk]);
    $customer = createCustomer($this->shop->fresh());
    $webUser = createWebUser($customer);
    $choose = fn (?string $code) => \App\Actions\Retina\UI\Profile\UpdateRetinaProfile::make()->action($webUser->fresh(), ['display_currency' => $code]);

    $choose($czk);
    expect($webUser->fresh()->settings['display_currency'])->toBe($czk);

    expect(fn () => $choose($huf))->toThrow(ValidationException::class);
    expect($webUser->fresh()->settings['display_currency'])->toBe($czk);

    $product = createProduct($this->shop)[1];
    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer->fresh(), [
        'date'             => date('Y-m-d'),
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    $transaction = \App\Actions\Ordering\Transaction\StoreTransaction::make()->action($order, $product->historicAsset, [
        'quantity_ordered' => 2,
        'gross_amount'     => 12.34,
        'net_amount'       => 12.34,
    ]);
    $order->refresh();

    expect($order->currency_id)->toBe($this->shop->currency_id)
        ->and((float) $transaction->net_amount)->toBe(round((float) $product->price * 2, 2))
        ->and((float) $order->net_amount)->toBe((float) $order->transactions()->sum('net_amount'));

    $choose($this->shop->currency->code);
    expect($webUser->fresh()->settings['display_currency'])->toBeNull();

    asDropshippingShop($this->shop, false);
    expect(fn () => $choose($czk))->toThrow(ValidationException::class);

    setShopDisplayCurrencies($this->shop, null);
});

test('language readiness counts active products with a name and a description per language, the main language first', function () {
    [, $product] = createProduct($this->shop);
    [$otherCode] = otherLanguageCodes($this->shop);
    $product->state = \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE;
    $product->name  = 'Lavender soap';
    $product->setTranslation('name_i8n', $otherCode, '')->setTranslation('description_i8n', $otherCode, '')->saveQuietly();
    $row = fn (string $code) => collect(\App\Actions\Catalogue\Shop\UI\GetShopLanguageReadiness::run($this->shop->fresh()))->firstWhere('code', $code);

    $before = $row($otherCode) ?? ['names' => 0, 'descriptions' => 0];
    $product->setTranslation('name_i8n', $otherCode, 'Seife')->setTranslation('description_i8n', $otherCode, '  ')->saveQuietly();
    $after = $row($otherCode);
    $rows  = \App\Actions\Catalogue\Shop\UI\GetShopLanguageReadiness::run($this->shop->fresh());

    expect($after['names'])->toBe($before['names'] + 1)
        ->and($after['descriptions'])->toBe($before['descriptions'])
        ->and($after['products'])->toBe($this->shop->products()->where('state', \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE)->whereNull('deleted_at')->count())
        ->and($rows[0]['code'])->toBe($this->shop->language->code)
        ->and($rows[0]['names'])->toBeGreaterThanOrEqual(1);
});

afterEach(function () {
    request()->attributes->remove('website_translation_locale');
});

test('website texts typed into layouts are collected without links, styles, scripts or catalogue data, and shown translated string by string (INI-510)', function () {
    $layout = ['web_blocks' => [
        ['type' => 'text', 'web_block' => ['layout' => ['data' => ['fieldValue' => [
            'value'     => '<h2>Shop by Departments</h2>',
            'container' => ['properties' => ['background' => ['color' => 'rgb(1, 2, 3)'], 'text' => 'Ignored']],
        ]]]]],
        ['type' => 'images', 'web_block' => ['layout' => ['data' => ['fieldValue' => ['value' => ['images' => [
            ['caption' => 'Aromatherapy', 'source' => ['original' => 'https://media.aiku.io/x'], 'link_data' => ['data' => ['title' => 'Link title']], 'properties' => ['alt' => 'Ignored']],
        ]]]]]]],
        ['type' => 'text', 'web_block' => ['layout' => ['data' => ['fieldValue' => ['value' => ['desktop' => '<p>Wide text</p>', 'mobile' => null]]]]]],
        ['type' => 'script', 'web_block' => ['layout' => ['data' => ['fieldValue' => ['value' => '<script>var words = "no way";</script>']]]]],
        ['type' => 'product-1', 'web_block' => ['layout' => ['data' => ['fieldValue' => ['product' => ['name' => 'Lavender oil'], 'settings' => ['title' => 'Other family']]]]]],
    ]];
    $header = ['header' => ['name' => 'Header template', 'data' => ['fieldValue' => ['logo' => ['alt' => 'AW Dropship'], 'image' => 'photo.jpg']]], 'topBar' => ['data' => ['fieldValue' => [
        'cart' => ['text' => '{{ cart_count }} items', 'visible' => 'login'],
        'link' => 'https://example.com',
    ]]]];

    expect(array_values(\App\Helpers\WebsiteTexts::collect($layout)))->toBe(['<h2>Shop by Departments</h2>', 'Aromatherapy', '<p>Wide text</p>'])
        ->and(array_values(\App\Helpers\WebsiteTexts::collect($header)))->toBe(['AW Dropship', '{{ cart_count }} items']);

    [$otherCode] = otherLanguageCodes($this->shop);
    \App\Models\Web\WebsiteTextTranslation::create([
        'website_id' => $this->website->id, 'locale' => $otherCode, 'source_hash' => sha1('Aromatherapy'),
        'source_text' => 'Aromatherapy', 'text' => 'Aromatherapie', 'origin' => 'human',
    ]);

    expect(\App\Helpers\WebsiteTexts::forVisitor($this->website->id, $layout))->toBe($layout);

    \App\Helpers\WebsiteLanguage::set($this->shop, $otherCode);
    $translated = \App\Helpers\WebsiteTexts::forVisitor($this->website->id, $layout);

    expect(data_get($translated, 'web_blocks.1.web_block.layout.data.fieldValue.value.images.0.caption'))->toBe('Aromatherapie')
        ->and(data_get($translated, 'web_blocks.0.web_block.layout.data.fieldValue.value'))->toBe('<h2>Shop by Departments</h2>')
        ->and(\App\Actions\Iris\Json\GetIrisFooterData::cacheKey($this->website->id, $otherCode))->toBe('irisData:website:'.$this->website->id.':footer:'.$otherCode)
        ->and(\App\Actions\Iris\Json\GetIrisFooterData::cacheKey($this->website->id, null))->toBe('irisData:website:'.$this->website->id.':footer');

    $webpage = new Webpage(['title' => 'Aromatherapy', 'description' => 'Not translated yet']);
    $webpage->website_id = $this->website->id;
    expect(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'title'))->toBe('Aromatherapie')
        ->and(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'description'))->toBe('Not translated yet');

    $page = $this->website->webpages()->whereNull('model_id')->firstOrFail();
    $page->update(['title' => 'Aromatherapy', 'published_layout' => ['web_blocks' => [
        ['type' => 'text', 'show' => true, 'visibility' => ['in' => true, 'out' => true], 'web_block' => ['layout' => ['data' => ['fieldValue' => ['value' => 'Aromatherapy']]]]],
    ]]]);
    $rendered = \App\Actions\Web\Webpage\Iris\ShowIrisWebpage::make()->getWebpageData($page->id, [], false);

    expect(data_get($rendered, 'web_blocks.0.web_block.layout.data.fieldValue.value'))->toBe('Aromatherapie')
        ->and($rendered['webpage_data']['title'])->toContain('Aromatherapie');

    request()->attributes->remove('website_translation_locale');
});

test('website texts are machine translated only when new, never over an existing translation, and a broken translation is left out (INI-510)', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    $website = $this->website;
    \App\Actions\Web\Website\TranslateWebsiteTexts::make()->handle($website);
    expect(\App\Models\Web\WebsiteTextTranslation::where('website_id', $website->id)->count())->toBe(0);

    enableShopLanguages($this->shop, [$this->shop->language->code, $otherCode]);
    $website = $website->fresh();
    $published = $website->published_layout ?? [];
    $published['footer'] = ['status' => 'active', 'data' => ['fieldValue' => ['columns' => [
        ['name' => 'Customer Service'], ['name' => '<a href="https://x.test/delivery">Delivery</a>'], ['name' => 'Human column'],
        ['name' => '<!-- hero --><p class="x">Hello <img src="a.jpg?a=1&amp;b=2" alt="Oils"></p><style>.x{}</style>'],
    ]]]];
    $website->update(['published_layout' => $published]);

    \App\Models\Web\WebsiteTextTranslation::create([
        'website_id' => $website->id, 'locale' => $otherCode, 'source_hash' => sha1('Human column'),
        'source_text' => 'Human column', 'text' => 'Written by a person', 'origin' => 'human',
    ]);

    \App\Actions\Helpers\Translations\Translate::mock()->shouldReceive('many')->once()
        ->andReturnUsing(fn (array $texts) => array_map(fn (string $text) => match (true) {
            str_contains($text, '<a') => 'Lieferung',
            str_contains($text, 'Hello') => str_replace(['Hello', 'Oils'], ['Hallo', 'Öle'], $text),
            default => 'DE '.$text,
        }, $texts));

    $stats = \App\Actions\Web\Website\TranslateWebsiteTexts::make()->handle($website, marginal: 'footer');
    $rows  = \App\Models\Web\WebsiteTextTranslation::where('website_id', $website->id)->where('locale', $otherCode)->pluck('text', 'source_text')->all();

    expect($stats[$otherCode])->toBe(['texts' => 4, 'missing' => 3, 'written' => 2])
        ->and($rows['Human column'])->toBe('Written by a person')
        ->and($rows['Customer Service'])->toBe('DE Customer Service')
        ->and($rows['<!-- hero --><p class="x">Hello <img src="a.jpg?a=1&amp;b=2" alt="Oils"></p><style>.x{}</style>'])->toBe('<!-- hero --><p class="x">Hallo <img src="a.jpg?a=1&amp;b=2" alt="Öle"></p><style>.x{}</style>')
        ->and(\App\Helpers\WebsiteTexts::mask('<p class="x">Hi</p><script>a()</script>')[0])->toBe('<p data-k="1">Hi</p><!--aiku-keep-0-->')
        ->and(\App\Helpers\WebsiteTexts::mask('<span title="a > b" class="x">Hi</span>')[0])->toBe('<span data-k="0" title="a > b">Hi</span>')
        ->and(\App\Helpers\WebsiteTexts::unmask('<div data-k="0" title="Jetzt">x</div>', ['<div data-title="Sale" title="Sale now">']))->toBe('<div data-title="Sale" title="Jetzt">x</div>')
        ->and(\App\Helpers\WebsiteTexts::nestsLike('<b><i>x</i></b><img data-k="1">', '<b><i>x</b></i>'))->toBeFalse()
        ->and(\App\Helpers\WebsiteTexts::nestsLike('<b><i>x</i></b><br>', '<i>y</i><b>x</b><br>'))->toBeTrue()
        ->and(\App\Helpers\WebsiteTexts::pieces(str_repeat('<p>Some words</p>', 10), 40))->toHaveCount(5)
        ->and(implode('', \App\Helpers\WebsiteTexts::pieces(str_repeat('<p>Some words</p>', 10), 40)))->toBe(str_repeat('<p>Some words</p>', 10))
        ->and(\App\Helpers\WebsiteTexts::isWords('iris.catalogue.feeds.download_img'))->toBeFalse()
        ->and(\App\Helpers\WebsiteTexts::isWords('{{ favourites_count }}'))->toBeFalse();

    \App\Actions\Helpers\Translations\Translate::mock()->shouldNotReceive('many');
    $stats = \App\Actions\Web\Website\TranslateWebsiteTexts::make()->handle($website, marginal: 'footer', dryRun: true);
    expect($stats[$otherCode]['missing'])->toBe(1);

    Queue::fake();
    \App\Actions\Web\Website\TranslateWebsiteTexts::dispatchIfTranslated($website);
    Queue::assertPushedOn('low-priority', \App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Web\Website\TranslateWebsiteTexts);

    enableShopLanguages($this->shop, null);
});

test('banner slide texts and webpage breadcrumb labels and share image alts are translated for a visitor in another language (INI-510)', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    enableShopLanguages($this->shop, [$this->shop->language->code, $otherCode]);

    $layout = [
        'type'       => 'landscape',
        'common'     => ['centralStage' => ['title' => null]],
        'components' => [[
            'id'         => 1,
            'visibility' => true,
            'image'      => ['desktop' => ['name' => 'STARL desktop-banner.jpg', 'source' => ['original' => 'https://media.aiku.io/x']]],
            'layout'     => [
                'imageAlt'     => 'Paper star lamps',
                'centralStage' => ['title' => 'NEW IN', 'style' => ['color' => 'red']],
                'corners'      => [
                    'topRight'    => ['type' => 'cornerText', 'data' => ['title' => 'NEW IN', 'subtitle' => 'Paper Star Lamp Shade'], 'temporaryData' => ['cornerText' => ['title' => 'Old title']]],
                    'bottomRight' => ['type' => 'linkButton', 'data' => ['text' => 'LIST TODAY', 'target' => 'https://www.aw-dropship.eu/scanp']],
                ],
                'background'   => ['desktop' => 'linear-gradient(to left, rgb(1, 2, 3), rgb(4, 5, 6))'],
            ],
        ]],
        'navigation' => ['bottomNav' => ['type' => ['value' => 'bullets']]],
    ];

    $banner = \App\Actions\Web\Banner\StoreBanner::make()->action($this->website, ['name' => 'ini510', 'type' => \App\Enums\Web\Banner\BannerTypeEnum::LANDSCAPE, 'ratio' => '16/9']);
    $banner->update(['compiled_layout' => $layout]);

    expect(array_values(\App\Helpers\WebsiteTexts::collectBanner($layout)))->toEqualCanonicalizing(['Paper star lamps', 'NEW IN', 'Paper Star Lamp Shade', 'LIST TODAY']);

    \App\Actions\Helpers\Translations\Translate::mock()->shouldReceive('many')->once()
        ->andReturnUsing(fn (array $texts) => array_map(fn (string $text) => 'DE '.$text, $texts));
    $stats = \App\Actions\Web\Website\TranslateWebsiteTexts::make()->handle($this->website->fresh(), banner: $banner);
    expect($stats[$otherCode])->toBe(['texts' => 4, 'missing' => 4, 'written' => 4]);

    $webBlock = ['type' => 'banner', 'web_block' => ['layout' => ['data' => ['fieldValue' => ['banner_id' => $banner->id]]]]];
    $page     = $this->website->webpages()->whereNull('model_id')->firstOrFail();

    expect(\App\Helpers\WebsiteTexts::bannerForVisitor($this->website->id, $layout))->toBe($layout);

    \App\Helpers\WebsiteLanguage::set($this->shop, $otherCode);
    $shown = data_get(\App\Actions\Web\WebBlock\Iris\GetIrisWebBlockBanner::run($page, $webBlock), "web_block.layout.data.fieldValue.banners_data.$banner->id.compiled_layout");
    $json  = \App\Actions\Iris\Json\GetBanner::make()->handle($this->website, $banner->id)['compiled_layout'];

    expect(data_get($shown, 'components.0.layout.corners.topRight.data.title'))->toBe('DE NEW IN')
        ->and(data_get($shown, 'components.0.layout.corners.bottomRight.data.text'))->toBe('DE LIST TODAY')
        ->and(data_get($shown, 'components.0.layout.corners.bottomRight.data.target'))->toBe('https://www.aw-dropship.eu/scanp')
        ->and(data_get($shown, 'components.0.layout.imageAlt'))->toBe('DE Paper star lamps')
        ->and(data_get($shown, 'navigation.bottomNav.type.value'))->toBe('bullets')
        ->and(data_get($json, 'components.0.layout.centralStage.title'))->toBe('DE NEW IN');

    \App\Models\Web\WebsiteTextTranslation::create([
        'website_id' => $this->website->id, 'locale' => $otherCode, 'source_hash' => sha1('Gemstone Pictures'),
        'source_text' => 'Gemstone Pictures', 'text' => 'Edelsteinbilder', 'origin' => 'human',
    ]);
    expect(\App\Actions\Web\Website\TranslateWebsiteTexts::make()->websiteTexts($this->website->fresh()))->toContain('LIST TODAY', 'Paper Star Lamp Shade');

    request()->attributes->remove('website_texts:'.$this->website->id.':'.$otherCode);
    $webpage = new Webpage(['breadcrumb_label' => 'Gemstone Pictures', 'seo_data' => ['image_alt' => 'Gemstone Pictures']]);
    $webpage->website_id = $this->website->id;

    expect(\App\Helpers\WebsiteLanguage::webpageText($webpage, 'breadcrumb_label'))->toBe('Edelsteinbilder')
        ->and(\App\Actions\Web\Webpage\Iris\ShowIrisWebpage::make()->getWebpageShareImageAlt($webpage))->toBe('Edelsteinbilder')
        ->and(array_values(\App\Actions\Web\Website\TranslateWebsiteTexts::make()->webpageTexts($webpage)))->toBe(['Gemstone Pictures']);

    request()->attributes->remove('website_translation_locale');
    expect(\App\Actions\Web\Webpage\Iris\ShowIrisWebpage::make()->getWebpageShareImageAlt($webpage))->toBe('Gemstone Pictures');

    Queue::fake();
    \App\Actions\Web\Banner\PublishBanner::make()->handle($banner->fresh(), ['layout' => ['type' => 'landscape', 'components' => []]]);
    Queue::assertPushedOn('low-priority', \App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Web\Website\TranslateWebsiteTexts);

    enableShopLanguages($this->shop, null);
});

test('product tags and category FAQs are shown in the visitor language, and the FAQ texts are collected for translation (INI-510)', function () {
    [$otherCode] = otherLanguageCodes($this->shop);
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    $tag = \App\Models\Helpers\Tag::create(['group_id' => $this->shop->group_id, 'name' => 'Handmade', 'slug' => 'handmade-ini510', 'scope' => \App\Enums\Helpers\Tag\TagScopeEnum::PRODUCT_PROPERTY, 'label' => ['en' => 'Handmade', $otherCode => 'Handgemacht']]);
    $untranslated = \App\Models\Helpers\Tag::create(['group_id' => $this->shop->group_id, 'name' => 'Vegan', 'slug' => 'vegan-ini510', 'scope' => \App\Enums\Helpers\Tag\TagScopeEnum::PRODUCT_PROPERTY, 'label' => ['en' => 'Vegan']]);
    $faq = [['question' => 'Is it handmade?', 'answer' => '<p>Yes, by artisans.</p>'], ['question' => 'Not translated yet', 'answer' => '']];
    DB::table('product_categories')->where('id', $family->id)->update(['faq' => json_encode($faq)]);
    $family = $family->fresh();

    $webpage = new Webpage();
    $webpage->setRelation('model', $family);
    expect(array_values(\App\Actions\Web\Website\TranslateWebsiteTexts::make()->webpageTexts($webpage)))->toContain('Is it handmade?', '<p>Yes, by artisans.</p>', 'Not translated yet')
        ->and(\App\Helpers\WebsiteLanguage::faqTexts($faq))->toHaveCount(3);

    foreach (['Is it handmade?' => 'Ist es handgemacht?', '<p>Yes, by artisans.</p>' => '<p>Ja, von Kunsthandwerkern.</p>'] as $source => $text) {
        \App\Models\Web\WebsiteTextTranslation::create([
            'website_id' => $this->website->id, 'locale' => $otherCode, 'source_hash' => sha1($source),
            'source_text' => $source, 'text' => $text, 'origin' => 'machine',
        ]);
    }

    expect(\App\Helpers\WebsiteLanguage::tagName($tag))->toBe('Handmade')
        ->and(\App\Helpers\WebsiteLanguage::faq($this->website->id, $faq))->toBe($faq);

    DB::table('model_has_tags')->insert(['tag_id' => $tag->id, 'model_type' => 'Product', 'model_id' => $product->id, 'shop_id' => $this->shop->id]);
    $shopTags = function () {
        $action = \App\Actions\Helpers\Tag\Json\GetIrisShopTags::make();
        (fn () => $this->shop = test()->shop)->call($action);

        return collect($action->jsonResponse($action->handle())->toArray(request()))->pluck('name')->all();
    };
    expect($shopTags())->toBe(['Handmade']);

    \App\Helpers\WebsiteLanguage::set($this->shop, $otherCode);

    expect($shopTags())->toBe(['Handgemacht'])
        ->and(\App\Helpers\WebsiteLanguage::tagName($tag))->toBe('Handgemacht')
        ->and(\App\Helpers\WebsiteLanguage::tagName($untranslated))->toBe('Vegan')
        ->and(\App\Http\Resources\Catalogue\TagResource::make($tag)->toArray(request())['name'])->toBe('Handgemacht')
        ->and(\App\Http\Resources\Catalogue\TagsResource::make(\App\Models\Helpers\Tag::select(['id', 'name', 'slug'])->find($tag->id))->toArray(request())['name'])->toBe('Handmade')
        ->and(\App\Helpers\WebsiteLanguage::faq($this->website->id, $faq))->toBe([
            ['question' => 'Ist es handgemacht?', 'answer' => '<p>Ja, von Kunsthandwerkern.</p>'],
            ['question' => 'Not translated yet', 'answer' => ''],
        ]);

    request()->attributes->remove('website_translation_locale');
});

test('staff correct the website languages of a product, a category and a website text, and nothing replaces them', function () {
    Bus::fake();
    [$otherCode] = otherLanguageCodes($this->shop);
    enableShopLanguages($this->shop, [$this->shop->language->code, $otherCode]);
    $shop = $this->shop->fresh();
    [, $product] = createProduct($shop);
    $family      = $product->family;

    DB::table('products')->where('id', $product->id)->update([
        'name_i8n'        => json_encode([$otherCode => 'Machine name', 'xx' => 'Kept']),
        'description_i8n' => json_encode([$otherCode => '<p>Machine description</p>']),
    ]);
    DB::table('product_categories')->where('id', $family->id)->update(['name_i8n' => json_encode([$otherCode => 'Machine family', 'xx' => 'Kept'])]);

    $section = \App\Actions\Catalogue\Product\UI\EditProduct::make()->websiteTranslationSection($product->fresh(), $shop);
    expect($section['fields']['name_i8n']['value'])->toBe([$otherCode => 'Machine name'])
        ->and($section['fields']['description_i8n']['html'])->toBeTrue();

    $product = \App\Actions\Catalogue\Product\UpdateProduct::make()->action($product->fresh(), [
        'name_i8n'        => [$otherCode => 'Staff name'],
        'description_i8n' => [$otherCode => '<p> </p>'],
    ]);
    $family = \App\Actions\Catalogue\ProductCategory\UpdateProductCategory::make()->action($family->fresh(), ['name_i8n' => [$otherCode => 'Staff family']]);

    expect($product->fresh()->getTranslations('name_i8n'))->toBe([$otherCode => 'Staff name', 'xx' => 'Kept'])
        ->and($product->fresh()->getTranslations('description_i8n'))->toBe([])
        ->and($family->fresh()->getTranslations('name_i8n'))->toBe([$otherCode => 'Staff family', 'xx' => 'Kept']);

    $row = \App\Models\Web\WebsiteTextTranslation::create([
        'website_id' => $this->website->id, 'locale' => $otherCode, 'source_hash' => sha1('<p class="x">Last seen</p>'),
        'source_text' => '<p class="x">Last seen</p>', 'text' => '<p class="x">Machine</p>', 'origin' => 'machine',
    ]);

    expect(fn () => \App\Actions\Web\Website\UpdateWebsiteTextTranslation::make()->handle($row, ['text' => 'No tags']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    \App\Actions\Web\Website\UpdateWebsiteTextTranslation::make()->handle($row, ['text' => '<p class="x">Zuletzt angesehen</p>']);
    \App\Models\Web\WebsiteTextTranslation::insertOrIgnore([[
        'website_id' => $this->website->id, 'locale' => $otherCode, 'source_hash' => $row->source_hash,
        'source_text' => $row->source_text, 'text' => 'AI again', 'origin' => 'machine', 'created_at' => now(), 'updated_at' => now(),
    ]]);

    expect($row->fresh()->text)->toBe('<p class="x">Zuletzt angesehen</p>')
        ->and($row->fresh()->origin)->toBe('human');

    enableShopLanguages($shop, [$shop->language->code]);
    expect(\App\Actions\Catalogue\Product\UI\EditProduct::make()->websiteTranslationSection($product, $shop->fresh()))->toBeNull();
});
