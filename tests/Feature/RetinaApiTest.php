<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 00:00:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\ApproveCustomer;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

beforeEach(
    /**
     * @throws \Throwable
     */
    function () {
        $this->organisation = createOrganisation();
        $this->group        = $this->organisation->group;

        $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        $dropshippingShop = Shop::where('type', ShopTypeEnum::DROPSHIPPING)->first();
        if (!$dropshippingShop) {
            $storeData = Shop::factory()->definition();
            data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);
            $dropshippingShop = StoreShop::make()->action($this->organisation, $storeData);
        }
        $this->dropshippingShop = UpdateShop::make()->action($dropshippingShop, ['state' => ShopStateEnum::OPEN]);

        $this->dropshippingCustomer = createCustomer($this->dropshippingShop);
        list($this->tradeUnit, $this->product) = createProduct($this->dropshippingShop);
        $this->product = UpdateProduct::make()->action($this->product, ['status' => ProductStatusEnum::FOR_SALE]);

        $this->dropshippingChannel = StoreCustomerSalesChannel::make()->action(
            $this->dropshippingCustomer,
            $platform,
            []
        );

        $this->warehouse = createWarehouse();
        $this->fulfilment = createFulfilment($this->organisation);
        $this->fulfilmentShop = UpdateShop::make()->action($this->fulfilment->shop, ['state' => ShopStateEnum::OPEN]);
        list($this->fulfilmentTradeUnit, $this->fulfilmentProduct) = createProduct($this->fulfilmentShop);

        $this->fulfilmentCustomer = createCustomer($this->fulfilmentShop);
        ApproveCustomer::make()->action($this->fulfilmentCustomer, []);
        $this->fulfilmentCustomer->refresh();

        $this->fulfilmentChannel = StoreCustomerSalesChannel::make()->action(
            $this->fulfilmentCustomer,
            $platform,
            []
        );
    }
);

// ---- Profile ----

test('retina api get profile', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $response = getJson(route('retina.api.profile'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'reference', 'name', 'email'],
    ]);
});

test('retina api get profile unauthenticated', function () {
    $response = getJson(route('retina.api.profile'));
    $response->assertUnauthorized();
    expect($response->json('message'))->toContain('Authorization: Bearer');
});

test('retina api dropshipping images rejects non integer id with validation error', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read']);

    getJson(route('retina.api.dropshipping.images.index', ['id' => 'not-a-number', 'type' => 'product']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id']);
});

// ---- Dropshipping: clients ----

test('retina api dropshipping store client', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $response = postJson(route('retina.api.dropshipping.clients.create'), CustomerClient::factory()->definition());
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);
});

test('retina api read only token can read but not write', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read']);

    getJson(route('retina.api.dropshipping.clients.index'))->assertOk();

    postJson(route('retina.api.dropshipping.clients.create'), CustomerClient::factory()->definition())
        ->assertForbidden()
        ->assertJsonFragment(['message' => 'This API token is read only.']);
});

test('retina api legacy token without split abilities is full access after backfill', function () {
    $token = $this->dropshippingChannel->createToken('legacy', ['retina']);
    DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->update(['abilities' => '["retina"]']);

    (require database_path('migrations/2026_08_23_200000_backfill_retina_api_token_abilities.php'))->up();

    expect(json_decode(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->value('abilities')))
        ->toBe(['retina', 'retina:read', 'retina:write']);
});

test('retina api store token honours read_only flag', function () {
    $plain = \App\Actions\Retina\Dropshipping\ApiToken\StoreCustomerToken::make()->handle($this->dropshippingChannel, true);
    $id = explode('|', $plain)[0];

    expect(json_decode(DB::table('personal_access_tokens')->where('id', $id)->value('abilities')))->toBe(['retina', 'retina:read'])
        ->and(DB::table('personal_access_tokens')->where('id', $id)->value('name'))->toContain('(read only)');
});

test('retina api dropshipping clients flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $response = getJson(route('retina.api.dropshipping.clients.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'ulid', 'reference', 'name', 'email']],
    ]);

    $response = getJson(route('retina.api.dropshipping.clients.show', $client));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.clients.update', $client), [
        'contact_name' => 'Updated Contact',
    ]);
    $response->assertOk();
    expect($response->json('data.contact_name'))->toBe('Updated Contact');

    $response = deleteJson(route('retina.api.dropshipping.clients.delete', $client));
    $response->assertOk();
    expect($client->refresh()->status)->toBeFalse();
});

// ---- Dropshipping: orders ----

test('retina api dropshipping orders flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $response = postJson(route('retina.api.dropshipping.order.store', $client));
    $response->assertOk();
    $orderId = $response->json('data.id');
    $order   = Order::find($orderId);

    $response = getJson(route('retina.api.dropshipping.order.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state']],
    ]);

    $response = getJson(route('retina.api.dropshipping.order.show', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state', 'customer'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.order.update', $order), [
        'public_notes' => 'Please ship fast',
    ]);
    $response->assertOk();

    $response = deleteJson(route('retina.api.dropshipping.order.delete', $order));
    $response->assertOk();
});

test('retina api dropshipping order submit', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $order = StoreOrder::make()->action($client, [
        'platform_id'               => $this->dropshippingChannel->platform_id,
        'customer_sales_channel_id' => $this->dropshippingChannel->id,
    ]);

    $portfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
        $this->dropshippingChannel,
        $this->product,
        []
    );

    postJson(route('retina.api.dropshipping.order.transaction.store', [$order, $portfolio]), [
        'quantity_ordered' => 2,
    ])->assertCreated();

    DB::table('customers')->where('id', $this->dropshippingCustomer->id)->update(['balance' => 1000]);

    $response = patchJson(route('retina.api.dropshipping.order.submit', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'state'],
    ]);

    /** The endpoint charged the order and returned "submitted successfully" without ever calling
     * SubmitOrder once (commit b32903604a, 2 to 4 Sep 2026, HELP-3064). A 200 is not enough,
     * assert the order actually left the basket. */
    $order->refresh();
    expect($order->submitted_at)->not->toBeNull()
        ->and($order->state)->not->toBe(OrderStateEnum::CREATING);
});

// ---- Dropshipping: products & portfolios ----

test('retina api dropshipping index products', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $response = getJson(route('retina.api.dropshipping.products.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'price']],
    ]);
});

test('retina api dropshipping portfolio flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $response = postJson(route('retina.api.dropshipping.products.my_product.store', $this->product));
    $response->assertCreated();
    $portfolioId = $response->json('data.id');

    $response = getJson(route('retina.api.dropshipping.products.my_product.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name']],
    ]);

    $response = getJson(route('retina.api.dropshipping.products.my_product.show', $portfolioId));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'name'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.products.my_product.update', $portfolioId), [
        'customer_product_name' => 'My Custom Product',
    ]);
    $response->assertOk();
    expect($response->json('data.customer_product_name'))->toBe('My Custom Product');

    $response = deleteJson(route('retina.api.dropshipping.products.my_product.delete', $portfolioId));
    $response->assertOk();
});

// ---- Dropshipping: order transactions ----

test('retina api dropshipping order transactions flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $order = StoreOrder::make()->action($client, [
        'platform_id'               => $this->dropshippingChannel->platform_id,
        'customer_sales_channel_id' => $this->dropshippingChannel->id,
    ]);

    $portfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
        $this->dropshippingChannel,
        $this->product,
        []
    );

    $response = postJson(route('retina.api.dropshipping.order.transaction.store', [$order, $portfolio]), [
        'quantity_ordered' => 2,
    ]);
    $response->assertCreated();
    $transactionId = $response->json('data.id');

    $response = getJson(route('retina.api.dropshipping.order.transaction.index', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'quantity_ordered']],
    ]);

    $response = patchJson(route('retina.api.dropshipping.transaction.update', $transactionId), [
        'quantity_ordered' => 3,
    ]);
    $response->assertOk();
    expect($response->json('data.quantity_ordered'))->toBe(3);

    $response = deleteJson(route('retina.api.dropshipping.transaction.delete', $transactionId));
    $response->assertOk();
});

test('retina api dropshipping data feed csv', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    $response = getJson(route('retina.api.dropshipping.data_feed.csv'));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('retina api data feed csv escapes quotes in descriptions', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    UpdateProduct::make()->action($this->product, [
        'description' => 'Bochník <span style=\\"font-family: Comfortaa, sans-serif;\\">mýdla</span> se "závěsem"',
    ]);
    \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
        $this->dropshippingChannel,
        $this->product,
        []
    );

    $response = getJson(route('retina.api.dropshipping.data_feed.csv'));
    $response->assertOk();

    $lines = array_filter(explode("\n", trim($response->getContent())));
    $columnCounts = array_map(fn (string $line) => count(str_getcsv($line, ',', '"', '')), $lines);
    expect(array_unique($columnCounts))->toHaveCount(1);
});

// ---- Fulfilment: clients ----

test('retina api fulfilment clients flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->fulfilmentChannel,
        CustomerClient::factory()->definition()
    );

    $response = getJson(route('retina.api.fulfilment.clients.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'ulid', 'reference', 'name']],
    ]);

    $response = getJson(route('retina.api.fulfilment.clients.show', $client));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.clients.update', $client), [
        'contact_name' => 'Updated Fulfilment Contact',
    ]);
    $response->assertOk();
    expect($response->json('data.contact_name'))->toBe('Updated Fulfilment Contact');

    $response = deleteJson(route('retina.api.fulfilment.clients.delete', $client));
    $response->assertOk();
    expect($client->refresh()->status)->toBeFalse();
});

test('retina api fulfilment store client', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina', 'retina:read', 'retina:write']);

    $response = postJson(route('retina.api.fulfilment.clients.create'), CustomerClient::factory()->definition());
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);
});

// ---- Fulfilment: orders ----

test('retina api fulfilment orders flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina', 'retina:read', 'retina:write']);

    $client = StoreCustomerClient::make()->action(
        $this->fulfilmentChannel,
        CustomerClient::factory()->definition()
    );

    $response = postJson(route('retina.api.fulfilment.order.store', $client));
    $response->assertCreated();
    $palletReturnId = $response->json('data.id');
    $palletReturn    = \App\Models\Fulfilment\PalletReturn::find($palletReturnId);

    $response = getJson(route('retina.api.fulfilment.order.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state']],
    ]);

    $response = getJson(route('retina.api.fulfilment.order.show', $palletReturn));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.order.update', $palletReturn), [
        'customer_notes' => 'Handle with care',
    ]);
    $response->assertOk();

    $response = patchJson(route('retina.api.fulfilment.order.submit', $palletReturn));
    $response->assertUnprocessable();
    $response->assertJsonPath('message', 'Please attach at least one transaction to the order.');

    $response = postJson(route('retina.api.fulfilment.order.cancel', $palletReturn));
    $response->assertUnprocessable();
    $response->assertJsonPath('message', 'This Order is already in the "in_process" state and cannot be updated.');
});

// ---- Fulfilment: portfolios ----

test('retina api fulfilment portfolio flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina', 'retina:read', 'retina:write']);

    $storedItem = \App\Actions\Fulfilment\StoredItem\StoreStoredItem::make()->action(
        $this->fulfilmentCustomer->fulfilmentCustomer,
        ['reference' => 'api-portfolio-item']
    );
    $storedItem->update(['state' => \App\Enums\Fulfilment\StoredItem\StoredItemStateEnum::ACTIVE]);

    $response = postJson(route('retina.api.fulfilment.portfolios.store'));
    $response->assertSuccessful();

    $portfolio = $this->fulfilmentChannel->portfolios()->where('item_id', $storedItem->id)->first();
    expect($portfolio)->not->toBeNull();
    $portfolioId = $portfolio->id;

    $response = getJson(route('retina.api.fulfilment.portfolios.index'));
    $response->assertOk();

    $response = getJson(route('retina.api.fulfilment.portfolios.show', $portfolioId));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.portfolios.update', $portfolioId), [
        'customer_product_name' => 'My Fulfilment Product',
    ]);
    $response->assertOk();
    expect($response->json('data.customer_product_name'))->toBe('My Fulfilment Product');

    $response = deleteJson(route('retina.api.fulfilment.portfolios.delete', $portfolioId));
    $response->assertOk();
});
test('retina api real bearer token authenticates', function () {
    $plain = \App\Actions\Retina\Dropshipping\ApiToken\StoreCustomerToken::make()->handle($this->dropshippingChannel);

    getJson(route('retina.api.profile'), ['Authorization' => 'Bearer '.$plain])->assertOk();
});

test('retina api requests are logged with credentials redacted', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    postJson(route('retina.api.dropshipping.clients.create'), [
        'first_name' => 'Api',
        'last_name'  => 'Logged',
        'email'      => 'api-logged@example.com',
        'password'   => 'super-secret',
    ]);

    $logged = \App\Models\CRM\RetinaApiRequest::where('customer_id', $this->dropshippingCustomer->id)
        ->orderByDesc('id')->first();

    expect($logged)->not->toBeNull()
        ->and($logged->method)->toBe('POST')
        ->and($logged->customer_sales_channel_id)->toBe($this->dropshippingChannel->id)
        ->and($logged->duration_ms)->not->toBeNull()
        ->and($logged->payload['first_name'])->toBe('Api')
        ->and($logged->payload['password'])->toBe('***');
});

test('retina api logs query string arguments', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    getJson(route('retina.api.dropshipping.images.index', ['id' => 999999999, 'type' => 'product', 'token' => 'leaked']));

    $logged = \App\Models\CRM\RetinaApiRequest::where('customer_id', $this->dropshippingCustomer->id)
        ->orderByDesc('id')->first();

    expect($logged->route_parameters['id'])->toBe('999999999')
        ->and($logged->route_parameters['type'])->toBe('product')
        ->and($logged->route_parameters['token'])->toBe('***')
        ->and($logged->message)->toBe('Product not found');
});

test('retina api images are scoped to the calling customer', function () {
    $otherPortfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
        $this->fulfilmentChannel,
        $this->fulfilmentProduct,
        []
    );

    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    getJson(route('retina.api.dropshipping.images.index', ['id' => $otherPortfolio->id, 'type' => 'portfolio']))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Portfolio not found']);
});

test('retina api failures keep the response message', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);

    postJson(route('retina.api.dropshipping.clients.create'), [])->assertStatus(422);

    $logged = \App\Models\CRM\RetinaApiRequest::where('customer_id', $this->dropshippingCustomer->id)
        ->orderByDesc('id')->first();

    expect($logged->status)->toBe(422)
        ->and($logged->message)->not->toBeNull();
});

test('retina api requests are pruned after the retention window and capped per customer', function () {
    \App\Models\CRM\RetinaApiRequest::insert([
        [
            'customer_id' => $this->dropshippingCustomer->id,
            'method'      => 'GET',
            'path'        => 'app/re-api/user-profile',
            'status'      => 200,
            'created_at'  => now()->subDays(\App\Actions\CRM\Customer\PruneRetinaApiRequests::RETENTION_DAYS + 1),
        ],
        [
            'customer_id' => $this->dropshippingCustomer->id,
            'method'      => 'GET',
            'path'        => 'app/re-api/user-profile',
            'status'      => 200,
            'created_at'  => now(),
        ],
    ]);

    \App\Actions\CRM\Customer\PruneRetinaApiRequests::run();

    expect(\App\Models\CRM\RetinaApiRequest::where('created_at', '<', now()->subDays(\App\Actions\CRM\Customer\PruneRetinaApiRequests::RETENTION_DAYS))->count())->toBe(0)
        ->and(\App\Models\CRM\RetinaApiRequest::where('customer_id', $this->dropshippingCustomer->id)->count())->toBeGreaterThan(0)
        ->and(\App\Models\CRM\RetinaApiRequest::where('customer_id', $this->dropshippingCustomer->id)->count())->toBeLessThanOrEqual(\App\Actions\CRM\Customer\PruneRetinaApiRequests::CAP_PER_CUSTOMER);
});

test('retina api requests index only shows the customer own calls', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);
    getJson(route('retina.api.profile'))->assertOk();

    \App\Models\CRM\RetinaApiRequest::create([
        'customer_id' => $this->fulfilmentCustomer->id,
        'method'      => 'GET',
        'path'        => 'app/re-api/user-profile',
        'status'      => 200,
    ]);

    $requests = \App\Actions\Retina\Dropshipping\ApiToken\UI\IndexRetinaApiRequests::run($this->dropshippingCustomer);

    expect($requests->total())->toBeGreaterThan(0)
        ->and($requests->pluck('customer_id')->unique()->all())->toBe([$this->dropshippingCustomer->id]);
});

test('api inflow monitor alerts discord when a customer floods', function () {
    config()->set('services.discord.webhook_url', 'https://discord.test/webhook');
    \Illuminate\Support\Facades\Http::fake();

    $monitor = \App\Actions\DevOps\MonitorRetinaApiInflow::class;
    expect($monitor::run())->toBe([]);

    $rows = [];
    for ($i = 0; $i <= $monitor::CUSTOMER_HOURLY_THRESHOLD; $i++) {
        $rows[] = [
            'customer_id' => $this->dropshippingCustomer->id,
            'method'      => 'GET',
            'path'        => 'app/re-api/dropshipping/products',
            'status'      => $i % 2 ? 200 : 500,
            'created_at'  => now(),
        ];
    }
    \Illuminate\Support\Facades\DB::table('retina_api_requests')->insert($rows);

    $issues = $monitor::run();

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toContain((string) $this->dropshippingCustomer->id);

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request['content'], 'API Inflow Alert'));
});

test('a paid order that fails to submit raises an alert', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina', 'retina:read', 'retina:write']);
    config(['services.discord.webhook_url' => 'https://discord.test/hook']);
    Http::fake();

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );
    $order = StoreOrder::make()->action($client, [
        'platform_id'               => $this->dropshippingChannel->platform_id,
        'customer_sales_channel_id' => $this->dropshippingChannel->id,
    ]);
    $portfolio = StorePortfolio::make()->action($this->dropshippingChannel, $this->product, []);
    postJson(route('retina.api.dropshipping.order.transaction.store', [$order, $portfolio]), [
        'quantity_ordered' => 2,
    ])->assertCreated();

    DB::table('customers')->where('id', $this->dropshippingCustomer->id)->update(['balance' => 1000]);

    SubmitOrder::mock()->shouldReceive('action')->andThrow(
        ValidationException::withMessages(['order' => 'Order has been submitted and cannot be submitted again'])
    );

    patchJson(route('retina.api.dropshipping.order.submit', $order));

    $order->refresh();
    expect($order->pay_status)->toBe(OrderPayStatusEnum::PAID)
        ->and($order->submitted_at)->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/hook'
        && str_contains($request['content'], $order->reference));
});
