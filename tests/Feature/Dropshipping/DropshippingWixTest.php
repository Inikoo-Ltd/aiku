<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Wix\Order\GetWixOrdersFromApi;
use App\Actions\Dropshipping\Wix\Order\StoreWixOrder;
use App\Actions\Dropshipping\Wix\Order\ValidateIncomingWixOrder;
use App\Actions\Dropshipping\Wix\User\AuthenticateWixAccount;
use App\Actions\Dropshipping\Wix\User\StoreWixUser;
use App\Actions\Dropshipping\Wix\Webhooks\HandleWixAppInstanceInstalled;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WixUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action($this->organisation, $storeData);
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    list(, $this->product) = createProduct($this->shop);

    Http::fake();
});

function wixChannel(Customer $customer): WixUser
{
    return StoreWixUser::make()->action($customer, [
        'wix_instance_id' => (string) Str::uuid(),
        'name'            => 'Wix site '.Str::random(4),
    ]);
}

function wixApprovedOrder(string $catalogItemId, string $appId = '215238eb-22a5-4c36-9e7b-e7c08025e04e'): array
{
    return [
        'id'        => (string) Str::uuid(),
        'status'    => 'APPROVED',
        'buyerInfo' => ['email' => 'buyer@example.com'],
        'lineItems' => [
            ['catalogReference' => ['catalogItemId' => $catalogItemId, 'appId' => $appId]],
        ],
    ];
}

test('a Wix order without our products, such as a booking, is not logged nor imported', function () {
    $wixUser = wixChannel($this->customer);
    StoreWixOrder::shouldRun()->never();

    ValidateIncomingWixOrder::run($wixUser, wixApprovedOrder((string) Str::uuid(), '13d21c63-b5ec-5912-8397-c3a5ddb27a97'));

    expect($wixUser->debugWebhooks()->count())->toBe(0);
});

test('a Wix order with our products is logged and imported', function () {
    $wixUser   = wixChannel($this->customer);
    $portfolio = StorePortfolio::make()->action($wixUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['platform_product_id' => (string) Str::uuid()]);
    StoreWixOrder::shouldRun()->once();

    ValidateIncomingWixOrder::run($wixUser, wixApprovedOrder($portfolio->platform_product_id));

    expect($wixUser->debugWebhooks()->count())->toBe(1);
});

function wixSignedInstance(string $instanceId): string
{
    $payload = rtrim(strtr(base64_encode(json_encode(['instanceId' => $instanceId])), '+/', '-_'), '=');

    return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, config('services.wix.app_secret'), true)), '+/', '-_'), '=').'.'.$payload;
}

test('the Wix install webhook stores a new instance while holding its lock and reuses it on a reinstall (PRE-43)', function () {
    $instanceId  = (string) Str::uuid();
    $lockWasHeld = null;
    Event::listen('eloquent.creating: '.WixUser::class, function (WixUser $wixUser) use (&$lockWasHeld, $instanceId) {
        if ($wixUser->wix_instance_id === $instanceId) {
            $lockWasHeld = !Cache::lock('wix-instance:'.$instanceId)->get();
        }
    });

    $orphan = HandleWixAppInstanceInstalled::make()->handle($instanceId);

    expect($lockWasHeld)->toBeTrue()
        ->and($orphan->customer_id)->toBeNull();

    expect(HandleWixAppInstanceInstalled::make()->handle($instanceId)->id)->toBe($orphan->id)
        ->and(WixUser::where('wix_instance_id', $instanceId)->count())->toBe(1);
});

test('a second account cannot link a Wix site another account already linked (PRE-43)', function () {
    config(['services.wix.app_secret' => 'pre-43-secret']);
    Http::swap(new HttpFactory());
    Http::fake([
        '*/oauth2/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*'              => Http::response([]),
    ]);

    $wixUser  = wixChannel($this->customer);
    $intruder = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $result = AuthenticateWixAccount::make()->handle(['instance' => wixSignedInstance($wixUser->wix_instance_id)], $intruder);

    expect($result)->toBe(__('This Wix site is already connected as a dropshipping channel'))
        ->and(WixUser::where('wix_instance_id', $wixUser->wix_instance_id)->pluck('customer_id')->all())->toBe([$this->customer->id]);
});

test('a Wix instance has one live wix_user, and a closed one does not block a reinstall (PRE-43)', function () {
    $wixUser = wixChannel($this->customer);

    expect(fn () => WixUser::create(['wix_instance_id' => $wixUser->wix_instance_id, 'name' => 'duplicate']))
        ->toThrow(UniqueConstraintViolationException::class);

    $wixUser->delete();

    expect(WixUser::create(['wix_instance_id' => $wixUser->wix_instance_id, 'name' => 'reinstall'])->exists)->toBeTrue();
});

test('the Wix order sweep only asks for orders placed since the channel was connected (PRE-43)', function () {
    Http::swap(new HttpFactory());
    Http::fake([
        '*/oauth2/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*'              => Http::response(['orders' => []]),
    ]);
    $wixUser = wixChannel($this->customer);

    GetWixOrdersFromApi::run($wixUser);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/ecom/v1/orders/search')
        && $request['search']['filter']['createdDate']['$gte'] === $wixUser->customerSalesChannel->created_at->toIso8601ZuluString());
});

test('a skipped Wix order is logged once however often the sweep sees it (PRE-43)', function () {
    $wixUser = wixChannel($this->customer);
    $order   = wixApprovedOrder((string) Str::uuid());
    Log::spy();

    ValidateIncomingWixOrder::run($wixUser, $order);
    ValidateIncomingWixOrder::run($wixUser, $order);

    Log::shouldHaveReceived('info')->with('Wix order skipped: none of its products is in the channel', Mockery::any())->once();
});

test('a Wix order whose basket was deleted is not imported and charged again by the sweep (PRE-43)', function () {
    $wixUser   = wixChannel($this->customer);
    $portfolio = StorePortfolio::make()->action($wixUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['platform_product_id' => (string) Str::uuid()]);
    $wixOrder = wixApprovedOrder($portfolio->platform_product_id);
    $deleted  = createOrder($this->customer, $this->product);
    $deleted->update(['platform_order_id' => $wixOrder['id']]);
    $deleted->delete();
    StoreWixOrder::shouldRun()->never();

    ValidateIncomingWixOrder::run($wixUser, $wixOrder);
});
