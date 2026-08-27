<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 16:30:57 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateEbayCustomerSalesChannel;
use App\Actions\Dropshipping\Tiktok\Product\UpdateInventoryTiktokProducts;
use App\Actions\Dropshipping\Tiktok\Product\UpdateTiktokInventory;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio;
use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Dropshipping\Ebay\StoreEbayUser;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\SysAdmin\Group\CreateAccessToken;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Helpers\ImgProxy\Image;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformStats;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group = $this->organisation->group;
    $this->user = createAdminGuest($this->group)->getUser();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );

    actingAs($this->user);
});



test('test platform were seeded', function () {
    expect($this->group->platforms()->count())->toBe(9);
    $platform = Platform::first();
    expect($platform)->toBeInstanceOf(Platform::class)
        ->and($platform->stats)->toBeInstanceOf(PlatformStats::class);

    $this->artisan('group:seed-platforms')->assertExitCode(0);
    expect($this->group->platforms()->count())->toBe(9);
});

test('add sales channel to customer', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();


    expect($this->customer->customerSalesChannels()->count())->toBe(0);
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        [
            'reference' => 'test_shopify_reference'
        ]
    );


    $customer = $customerSalesChannel->customer;


    expect($customer->customerSalesChannels()->first())->toBeInstanceOf(CustomerSalesChannel::class);


    return $customer;
});

test('create customer client', function () {
    $customerSalesChannel = $this->customer->customerSalesChannels()->first();

    $customerClient = StoreCustomerClient::make()->action(
        $customerSalesChannel,
        CustomerClient::factory()->definition()
    );

    expect($customerClient)->toBeInstanceOf(CustomerClient::class);

    return $customerClient;
});

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
})->depends('create customer client');

test('add product to customer portfolio', function () {



    $customerSalesChannel = $this->customer->customerSalesChannels()->first();
    expect($customerSalesChannel)->toBeInstanceOf(CustomerSalesChannel::class);
    $dropshippingCustomerPortfolio = StorePortfolio::make()->action(
        $customerSalesChannel,
        $this->product,
        []
    );
    expect($dropshippingCustomerPortfolio)->toBeInstanceOf(Portfolio::class);

    return $dropshippingCustomerPortfolio;
});


test('add product from another shop to customer portfolio is rejected', function () {
    $customerSalesChannel = $this->customer->customerSalesChannels()->first();
    $portfoliosBefore     = $customerSalesChannel->portfolios()->count();

    $productFromAnotherShop          = new Product();
    $productFromAnotherShop->shop_id = $this->shop->id + 1000;
    $productFromAnotherShop->code    = $this->product->code;

    expect(fn () => StorePortfolio::make()->action(
        $customerSalesChannel,
        $productFromAnotherShop,
        []
    ))->toThrow(ValidationException::class)
        ->and($customerSalesChannel->portfolios()->count())->toBe($portfoliosBefore);
});


test('add image to product', function () {
    Storage::fake('public');

    expect($this->product)->toBeInstanceOf(Product::class)
        ->and($this->product->images->count())->toBe(0);

    $fakeImage = UploadedFile::fake()->image('hello.jpg');
    $path      = $fakeImage->store('photos', 'public');

    SaveModelImages::run(
        model: $this->product,
        mediaData: [
            'path'         => Storage::disk('public')->path($path),
            'originalName' => $fakeImage->getClientOriginalName()

        ],
        mediaScope: 'product_images',
        modelHasMediaData: [
            'scope' => 'photo'
        ]
    );

    $this->product->refresh();

    expect($this->product)->toBeInstanceOf(Product::class)
        ->and($this->product->images->count())->toBe(1);
});

test('add 2nd image to product', function () {
    Storage::fake('public');

    $fakeImage2 = UploadedFile::fake()->image('hello2.jpg', 20, 20);


    $path2 = $fakeImage2->store('photos', 'public');

    SaveModelImages::run(
        model: $this->product,
        mediaData: [
            'path'         => Storage::disk('public')->path($path2),
            'originalName' => $fakeImage2->getClientOriginalName()
        ],
        mediaScope: 'product_images',
        modelHasMediaData: [
            'scope' => 'photo'
        ]
    );

    $this->product->refresh();

    expect($this->product)->toBeInstanceOf(Product::class)
        ->and($this->product->images->count())->toBe(2);
});

test('get product 1s1 images', function () {
    $media1 = $this->product->images->first();
    expect($media1)->toBeInstanceOf(Media::class);

    $image = $media1->getImage();
    expect($image)->toBeInstanceOf(Image::class);

    $imageSources1 = GetPictureSources::run($image);

    expect($imageSources1)->toBeArray()->toHaveCount(4);
})->depends('add 2nd image to product');

test('get product 2nd images and show resized sources', function () {
    $media2 = $this->product->images->last();
    expect($media2)->toBeInstanceOf(Media::class);


    $image2 = $media2->getImage()->resize(5, 5);
    expect($image2)->toBeInstanceOf(Image::class);

    $imageSources2 = GetPictureSources::run($image2);
    expect($imageSources2)->toBeArray()->toHaveCount(8);
})->depends('add 2nd image to product');


test('update customer portfolio', function (Portfolio $dropshippingCustomerPortfolio) {
    $dropshippingCustomerPortfolio = UpdatePortfolio::make()->action(
        $dropshippingCustomerPortfolio,
        [
            'reference' => 'new_reference'
        ]
    );
    expect($dropshippingCustomerPortfolio->reference)->toBe('new_reference');

    return $dropshippingCustomerPortfolio;
})->depends('add product to customer portfolio');


test('get dropshipping access token', function () {
    $token = CreateAccessToken::make()->action($this->group, ['name' => 'test_token', 'abilities' => ['bk-api']]);
    expect($token)->toBeString();
    $this->token = $token;
});

test('UI Index customer clients', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer             = $customerClient->customer;
    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index', [
        $customerClient->organisation->slug,
        $customerClient->shop->slug,
        $customerClient->customer->slug,
        $customerSalesChannel->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/CustomerClients')
            ->has('title')
            ->has('breadcrumbs', 5)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->depends('create customer client');


test('UI Show customer client', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();

    $customer             = $customerClient->customer;
    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerSalesChannel->slug,
        $customerClient->ulid
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customerClient) {
        $page
            ->component('Org/Shop/CRM/CustomerClient')
            ->has('title')
            ->has('breadcrumbs', 5)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customerClient->name)
                    ->has('subNavigation')
                    ->etc()
            );
    });
})->depends('create customer client');

test('UI create customer client', function (CustomerClient $customerClient) {
    $customer             = $customerClient->customer;
    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();

    $response = get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.create', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerSalesChannel->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 6);
    });
})->depends('create customer client');

test('UI edit customer client', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer             = $customerClient->customer;
    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();

    $response = get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.edit', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerSalesChannel->slug,
        $customerClient->ulid
    ]));
    $response->assertInertia(function (AssertableInertia $page) use ($customerClient) {
        $page
            ->component('EditModel')
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint', 1)
                    ->etc()
            )
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Edit client')
                    ->has('actions')
                    ->etc()
            )
            ->has('breadcrumbs', 6);
    });
})->depends('create customer client');

test('UI Index customer portfolios', function (CustomerClient $customerClient) {
    $customer             = Customer::first();
    /** @var CustomerSalesChannel $customerSalesChannel */
    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();

    $response = $this->get(
        route(
            'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $customer->slug,
                $customerSalesChannel->slug,
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Dropshipping/Portfolios')
            ->has('title')
            ->has('breadcrumbs', 5)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->depends('create customer client');

test('UI get section route client dropshipping', function (CustomerClient $customerClient) {
    $customer = $customerClient->customer;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index', [
        'organisation' => $customer->organisation->slug,
        'shop'         => $customer->shop->slug,
        'customer'     => $customer->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_CRM->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
})->depends('create customer client');


test('UI index customer client order', function () {
    $this->withoutExceptionHandling();
    $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        []
    );

    $customerClient = StoreCustomerClient::make()->action(
        $customerSalesChannel,
        CustomerClient::factory()->definition()
    );

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index', [
        $customerSalesChannel->organisation->slug,
        $customerSalesChannel->shop->slug,
        $customerSalesChannel->customer->slug,
        $customerSalesChannel->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Dropshipping/OrdersInCustomerSalesChannel')
            ->has('title')
            ->has('breadcrumbs', 5)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });

    return $customerClient;
});

test('UI index customer client portfolios', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer = $customerClient->customer;

    $customerSalesChannel = $customer->customerSalesChannels()->where('platform_id', $customerClient->platform_id)->first();
    $response             = $this->get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerSalesChannel->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Dropshipping/Portfolios')
            ->has('title')
            ->has('breadcrumbs', 5)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });

    return $customerClient;
})->depends('UI index customer client order');

test('UI index customer platforms', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer = $customerClient->customer;

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.customer_sales_channels.index', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Dropshipping/CustomerSalesChannels')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });

    return $customerClient;
})->depends('UI index customer client order');

test('Customer clients basket hydrator', function () {
    $customerClient = CustomerClient::first();
    CustomerClientHydrateBasket::run($customerClient->id);
    expect($customerClient)->toBeInstanceOf(CustomerClient::class)
        ->and($customerClient->amount_in_basket)->toEqual(0)
        ->and($customerClient->current_order_in_basket_id)->toBeNull();
});

test('Dropshipping hydrators', function () {
    $this->artisan('hydrate', [
        '--sections' => 'dropshipping',
    ])->assertExitCode(0);
});

test('ebay stock sync pushes when capped quantity differs from last push', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        ['reference' => 'test_ebay_stock_sync']
    );

    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);
    $portfolio->update(['platform_product_id' => 'ebay-offer-1', 'platform_status' => true]);

    $this->product->update([
        'available_quantity'            => 250,
        'available_quantity_updated_at' => now()->subMonths(2)
    ]);
    $customerSalesChannel->update(['max_quantity_advertise' => 80]);
    $product = $this->product->refresh();

    expect(UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(80);

    $customerSalesChannel->max_quantity_advertise = 0;
    expect(UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(250);
    $customerSalesChannel->max_quantity_advertise = 80;

    $customerSalesChannel->stock_threshold = 5;
    expect(UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(80);

    $customerSalesChannel->max_quantity_advertise = 5;
    expect(UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(5);
    $customerSalesChannel->max_quantity_advertise = 80;

    $this->product->update(['available_quantity' => 5]);
    expect(UpdateEbayPortfolio::quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(0);

    $this->product->update(['available_quantity' => 6]);
    expect(UpdateEbayPortfolio::quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(6);

    $customerSalesChannel->stock_threshold = 0;
    $this->product->update(['available_quantity' => 250]);
    $product = $this->product->refresh();

    $checker = UpdateInventoryInEbayPortfolio::make();

    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

    $portfolio->update(['last_stock_value' => 50, 'stock_last_updated_at' => now()->subMonths(6)]);
    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

    $portfolio->update(['last_stock_value' => 80]);
    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeFalse();

    $portfolio->update(['last_stock_value' => 50, 'stock_last_fail_updated_at' => now()->subHour()]);
    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeFalse()
        ->and($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel, true))->toBeTrue();

    $portfolio->update(['stock_last_fail_updated_at' => now()->subDays(2)]);
    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

    $portfolio->update(['last_stock_value' => null, 'stock_last_fail_updated_at' => null]);
    expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

    return $customerSalesChannel;
});

test('woo and tiktok stock sync push when capped quantity differs from last push', function () {
    $this->product->update([
        'available_quantity'            => 250,
        'available_quantity_updated_at' => now()->subMonths(2)
    ]);

    foreach ([
        [PlatformTypeEnum::WOOCOMMERCE, UpdateWooCustomerSalesChannelPortfolio::make(), fn ($p, $c) => UpdateWooCustomerSalesChannelPortfolio::quantityToSend($p, $c)],
        [PlatformTypeEnum::TIKTOK, UpdateInventoryTiktokProducts::make(), fn ($p, $c) => UpdateTiktokInventory::quantityToSend($p, $c)],
    ] as [$platformType, $checker, $quantityToSend]) {
        $platform = $this->group->platforms()->where('type', $platformType)->first();

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
            $this->customer,
            $platform,
            ['reference' => 'test_stock_sync_'.$platformType->value]
        );
        $customerSalesChannel->update(['max_quantity_advertise' => 80]);

        $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);
        $portfolio->update(['platform_product_id' => 'offer-'.$platformType->value, 'platform_status' => true]);

        expect($quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(80);

        $customerSalesChannel->stock_threshold = 5;
        $customerSalesChannel->max_quantity_advertise = 5;
        expect($quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(5);

        $this->product->update(['available_quantity' => 4]);
        expect($quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(0);

        $this->product->update(['available_quantity' => 250]);
        $customerSalesChannel->stock_threshold = 0;
        $customerSalesChannel->max_quantity_advertise = 80;

        expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

        $portfolio->update(['last_stock_value' => 50, 'stock_last_updated_at' => now()->subMonths(6)]);
        expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();

        $portfolio->update(['last_stock_value' => 80]);
        expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeFalse();

        $portfolio->update(['stock_last_fail_updated_at' => now()->subHour(), 'last_stock_value' => 50]);
        expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeFalse()
            ->and($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel, true))->toBeTrue();

        $portfolio->update(['last_stock_value' => null, 'stock_last_fail_updated_at' => null]);
        expect($checker->checkIfApplicable($portfolio->refresh(), $customerSalesChannel))->toBeTrue();
    }
});

test('updating woo and tiktok channel stock settings queues inventory sync', function () {
    Queue::fake();

    foreach ([
        [PlatformTypeEnum::WOOCOMMERCE, UpdateInventoryInWooPortfolio::class],
        [PlatformTypeEnum::TIKTOK, UpdateInventoryTiktokProducts::class],
    ] as [$platformType, $syncClass]) {
        $platform = $this->group->platforms()->where('type', $platformType)->first();

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
            $this->customer,
            $platform,
            ['reference' => 'test_settings_sync_'.$platformType->value]
        );

        $customerSalesChannel = UpdateCustomerSalesChannel::make()->action($customerSalesChannel, [
            'stock_update'           => true,
            'max_quantity_advertise' => 90
        ]);

        $syncClass::assertPushed(1);

        UpdateCustomerSalesChannel::make()->action($customerSalesChannel, [
            'stock_update'           => true,
            'max_quantity_advertise' => '90'
        ]);

        $syncClass::assertPushed(1);
    }
});

test('updating ebay channel stock settings queues inventory sync', function () {
    Queue::fake();

    $ebayUser = StoreEbayUser::make()->handle($this->customer, ['name' => 'test-ebay-user']);
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'stock_update'           => true,
        'max_quantity_advertise' => 90
    ]);
    UpdateInventoryInEbayPortfolio::assertPushed(1);

    UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'stock_update'           => true,
        'max_quantity_advertise' => '90'
    ]);

    UpdateInventoryInEbayPortfolio::assertPushed(1);
});

test('ebay channel do not update prices setting is stored', function () {
    $ebayUser = StoreEbayUser::make()->handle($this->customer, ['name' => 'test-ebay-user-prices']);
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'do_not_update_prices' => true
    ]);
    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'do_not_update_prices'))->toBeTrue();

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'do_not_update_prices' => false
    ]);
    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'do_not_update_prices'))->toBeFalse();
});

test('channel percent pricing rule prices new portfolios honestly', function () {
    $ebayUser = StoreEbayUser::make()->handle($this->customer, ['name' => 'test-ebay-pricing-store']);
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'pricing_type'  => 'percent',
        'pricing_value' => 20
    ]);

    $this->product->update(['rrp' => 10]);

    $portfolio = StorePortfolio::make()->action($customerSalesChannel->refresh(), $this->product, []);

    expect((float) $portfolio->customer_price)->toBe(12.0);
});

test('saving a channel pricing policy queues a reprice of every product', function () {
    Queue::fake();

    $ebayUser = StoreEbayUser::make()->handle($this->customer, ['name' => 'test-ebay-pricing-all']);
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'pricing_type'  => 'percent',
        'pricing_value' => 20
    ]);
    \App\Actions\Dropshipping\Ebay\Product\ApplyPricingRuleToEbayPortfolios::assertPushed(1);
    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'pricing.value'))->toBe(20);

    UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'pricing_type'  => 'percent',
        'pricing_value' => 20
    ]);
    \App\Actions\Dropshipping\Ebay\Product\ApplyPricingRuleToEbayPortfolios::assertPushed(1);
});

test('portfolio relative price rule computes price from rrp', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_price_rule']);
    $this->product->update(['rrp' => 10]);
    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, [
        'pricing_type'  => 'percent',
        'pricing_value' => -10
    ], true);

    $portfolio->refresh();
    expect((float) $portfolio->customer_price)->toBe(9.0)
        ->and(\Illuminate\Support\Arr::get($portfolio->settings, 'pricing.value'))->toBe(-10);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, [
        'pricing_type'  => 'fixed',
        'pricing_value' => 2.5
    ], true);

    $portfolio->refresh();
    expect((float) $portfolio->customer_price)->toBe(12.5);
});

test('portfolio not follow rule freezes price and opts out', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_not_follow']);
    $this->product->update(['rrp' => 10]);
    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, [
        'pricing_type' => 'not_follow'
    ], true);

    $portfolio->refresh();
    expect((float) $portfolio->customer_price)->toBe(10.0)
        ->and(\Illuminate\Support\Arr::get($portfolio->settings, 'pricing.type'))->toBe('not_follow')
        ->and(\Illuminate\Support\Arr::get($portfolio->settings, 'pricing_opt_out'))->toBeTrue();
});

test('pricing reset all overrides product opt outs', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_nuclear']);
    $this->product->update(['rrp' => 10]);
    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, [
        'pricing_type' => 'not_follow'
    ], true);

    $customerSalesChannel->update(['settings' => ['pricing' => ['type' => 'percent', 'value' => 50]]]);

    \App\Actions\Dropshipping\Ebay\Product\ApplyPricingRuleToEbayPortfolios::run($customerSalesChannel->refresh());
    expect((float) $portfolio->refresh()->customer_price)->toBe(10.0);

    \App\Actions\Dropshipping\Ebay\Product\ApplyPricingRuleToEbayPortfolios::run($customerSalesChannel, true);
    $portfolio->refresh();
    expect((float) $portfolio->customer_price)->toBe(15.0)
        ->and(\Illuminate\Support\Arr::get($portfolio->settings, 'pricing_opt_out'))->toBeNull()
        ->and(\Illuminate\Support\Arr::get($portfolio->settings, 'pricing'))->toBeNull();
});

test('ebay token refresh marks auth revoked only on invalid grant', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::sequence()
            ->push('oops', 500)
            ->push(['error' => 'invalid_grant'], 400)
    ]);

    $ebayUser = StoreEbayUser::make()->handle($this->customer, ['name' => 'test-ebay-auth-transient']);
    $ebayUser->settings = ['credentials' => ['ebay_refresh_token' => 'live-token']];

    $ebayUser->refreshEbayToken();
    expect($ebayUser->ebayAuthRevoked)->toBeFalse();

    $ebayUser->refreshEbayToken();
    expect($ebayUser->ebayAuthRevoked)->toBeTrue();
});

test('bulk price rule prices each product from its own rrp', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_bulk_price']);

    $this->product->update(['rrp' => 10]);
    $portfolioA = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    $family = $this->shop->productCategories()->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)->first();
    $productB = \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 50,
        ]
    ));
    $productB->update(['rrp' => 20]);
    $portfolioB = StorePortfolio::make()->action($customerSalesChannel, $productB, []);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel::run([
        'items'         => [$portfolioA->id, $portfolioB->id],
        'pricing_type'  => 'percent',
        'pricing_value' => -10
    ], true);

    expect((float) $portfolioA->refresh()->customer_price)->toBe(9.0)
        ->and((float) $portfolioB->refresh()->customer_price)->toBe(18.0)
        ->and(\Illuminate\Support\Arr::get($portfolioA->settings, 'pricing_opt_out'))->toBeTrue();
});
