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

    $outboxQueries = 0;
    DB::listen(function ($query) use (&$outboxQueries) {
        if (str_contains($query->sql, 'from "outboxes"')) {
            $outboxQueries++;
        }
    });

    $this->getJson('http://'.$this->website->domain.'/json/footer')->assertOk();
    expect($outboxQueries)->toBe(0);

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
        ->and($props['outboxes']())->toBe($expected);
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
        ->and($response->headers->get('X-AIKU-WEBSITE'))->toBe((string) $this->website->id);
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

    preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
    $page = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

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

test('iris attachment routes serve public product documents and labeling guides to logged-in customers only (HELP-4214)', function () {
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

    foreach ([$productSds, $tradeUnitIfra, $labelingGuide] as $media) {
        $this->get($baseUrl.$media->ulid)->assertNotFound();
        $this->get($baseUrl.$media->ulid.'/download')->assertNotFound();
    }

    $this->get($baseUrl.$webpageAttachment->ulid.'/download')->assertOk();

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

test('shopify docs explain selling own stock first and following our prices without changing yours', function () {
    $website = asDropshippingWebsite($this->website, 'dssk', 'en');

    expect(ShowIrisDoc::make()->handle($website, 'shopify-fulfilment-location')['doc']['html'])
        ->toContain('Selling your own stock of our products')
        ->toContain('Ranked locations')
        ->and(ShowIrisDoc::make()->handle($website, 'product-descriptions-after-connecting')['doc']['html'])
        ->toContain('Following our prices without changing yours')
        ->toContain('Price managed in your Shopify');
});

test('exporting product data doc says available quantity counts packs, with 250 for on demand', function () {
    $website = asDropshippingWebsite($this->website, 'dssk', 'en');

    expect(ShowIrisDoc::make()->handle($website, 'exporting-product-data')['doc']['html'])
        ->toContain('counted in the packs you order')
        ->toContain('always show 250')
        ->not->toContain('the number of units in stock');
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

test('iris family page does not ship the family products twice', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    $response = $this->withoutVite()->get('http://'.$this->website->domain.'/catalogue/family/'.$family->slug);
    $response->assertOk();

    preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
    $page = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

    expect($page['component'])->toBe('Catalogue/Family')
        ->and($page['props']['data']['family']['slug'])->toBe($family->slug)
        ->and($page['props']['data']['family'])->not->toHaveKey('products');
});

test('iris catalogue product page leaves out the rrp for visitors only (HELP-4214)', function () {
    [, $product] = createProduct($this->shop);
    $product->updateQuietly(['rrp' => 30]);
    $url = 'http://'.$this->website->domain.'/catalogue/products/'.$product->slug;

    $productProp = function () use ($url) {
        preg_match('/data-page="([^"]+)"/', $this->withoutVite()->get($url)->assertOk()->getContent(), $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true)['props']['data']['product'];
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

        return (new HandleIrisInertiaRequests())->share($request)['appointment_types'];
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
    $product->update(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'exclusive_for_customer_id' => null]);
    $department = $product->department;
    $family     = $product->family;
    $department->update(['state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE]);
    $family->update(['state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE]);
    $collection = \App\Actions\Catalogue\Collection\StoreCollection::make()->action($this->shop, ['code' => 'feed-'.uniqid(), 'name' => 'Feed collection']);
    $collection->update(['state' => \App\Enums\Catalogue\Collection\CollectionStateEnum::ACTIVE]);
    DB::table('collection_has_models')->insert(['collection_id' => $collection->id, 'model_type' => 'Product', 'model_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);

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
        expect(Storage::disk('data-feeds')->get(SaveDataFeeds::path($parent)))->toContain('Product code')->toContain($product->code);
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
