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
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

test('iris attachment routes only serve public product documents and labeling guides', function () {
    Storage::fake('local');
    [, $product] = createProduct($this->shop);
    $customer    = createCustomer($this->shop);
    $baseUrl     = 'http://'.$this->website->domain.'/attachment/';

    $productSds       = createAttachedMedia('Product', $product->id, 'sds');
    $tradeUnitIfra    = createAttachedMedia('TradeUnit', 1, 'ifra');
    $labelingGuide    = createAttachedMedia('TradeUnitFamily', 1, 'labeling_guide');
    $privateSds       = createAttachedMedia('TradeUnit', 1, 'sds_private');
    $employeeContract = createAttachedMedia('Employee', 1, 'Contract');
    $customerNote     = createAttachedMedia('Customer', $customer->id, 'CustomerNote');

    foreach ([$productSds, $tradeUnitIfra, $labelingGuide] as $media) {
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
