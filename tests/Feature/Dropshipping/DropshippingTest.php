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
use App\Actions\Dropshipping\Ebay\CallbackRetinaEbayUser;
use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Maintenance\Dropshipping\RepairEbayDuplicateChannels;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Models\Dropshipping\EbayUser;
use App\Actions\Dropshipping\Ebay\StoreEbayUser;
use App\Actions\Dropshipping\Ebay\Product\CheckEbayPortfolio;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Catalogue\Product\StoreProduct;
use Illuminate\Support\Str;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Dropshipping\Portfolio\CheckNewPortfoliosInPlatform;
use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Events\CloneRetinaPortfolioProgressEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Decorators\JobDecorator;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioItemsToChannels;
use App\Actions\Retina\UI\Dashboard\GetRetinaDropshippingHomeData;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
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
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformStats;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Arr;
use App\Actions\CRM\Customer\PdfCustomerLetterOfAuthorisation;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Models\SysAdmin\Guest;
use Spatie\Permission\Models\Permission;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function storeConnectedEbayUser(Customer $customer, string $name): EbayUser
{
    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => $name]);
    $ebayUser->update(['step' => EbayUserStepEnum::COMPLETED]);

    return $ebayUser->refresh();
}

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

test('pictures go to marketplaces main image first, then in the order arranged on the product', function () {
    $product = $this->product;
    $media   = collect(range(1, 4))->map(fn (int $i) => Media::create([
        'group_id'              => $product->group_id,
        'ulid'                  => \Illuminate\Support\Str::ulid(),
        'name'                  => "order-$i",
        'file_name'             => "order-$i.jpg",
        'disk'                  => 'public',
        'collection_name'       => 'default',
        'size'                  => 1,
        'manipulations'         => [],
        'custom_properties'     => [],
        'generated_conversions' => [],
        'responsive_images'     => [],
    ]));

    $product->images()->attach([
        $media[2]->id => ['position' => 1, 'scope' => 'photo', 'group_id' => $product->group_id, 'data' => '{}'],
        $media[0]->id => ['position' => 3, 'scope' => 'photo', 'group_id' => $product->group_id, 'data' => '{}'],
        $media[1]->id => ['position' => 2, 'scope' => 'photo', 'group_id' => $product->group_id, 'data' => '{}'],
        $media[3]->id => ['position' => 0, 'scope' => 'audio', 'group_id' => $product->group_id, 'data' => '{}'],
    ]);
    $previousImageId = $product->image_id;
    $product->update(['image_id' => $media[1]->id]);

    expect($product->orderedImages()->pluck('id')->intersect($media->pluck('id'))->values()->all())
        ->toBe([$media[1]->id, $media[2]->id, $media[0]->id]);

    $webImages = \App\Actions\Catalogue\Product\UpdateProductWebImages::run($product)->web_images['all'];
    expect(count($webImages))->toBe($product->orderedImages()->count())
        ->and(count($webImages))->toBe($product->images()->count() - 1);

    $product->images()->detach($media->pluck('id')->all());
    $product->update(['image_id' => $previousImageId]);
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


test('switching a portfolio off takes it out of its sales channel product count', function (Portfolio $portfolio) {
    $channel = $portfolio->customerSalesChannel;
    CustomerSalesChannelsHydratePortfolios::run($channel);
    $before = $channel->refresh()->number_portfolios;

    UpdatePortfolio::make()->action($portfolio, ['status' => false]);
    expect($channel->refresh()->number_portfolios)->toBe($before - 1);

    UpdatePortfolio::make()->action($portfolio->refresh(), ['status' => true]);
    expect($channel->refresh()->number_portfolios)->toBe($before);
})->depends('update customer portfolio');

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
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->intersect(['rrp', 'customer_price'])->count() === 2)
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

    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-user');
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
    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-user-prices');
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

test('ebay channel upload as draft setting is stored', function () {
    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-user-draft');
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'upload_as_draft'))->toBeNull();

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'upload_as_draft' => true
    ]);
    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'upload_as_draft'))->toBeTrue();

    $customerSalesChannel = UpdateEbayCustomerSalesChannel::make()->action($customerSalesChannel, [
        'upload_as_draft' => false
    ]);
    expect(\Illuminate\Support\Arr::get($customerSalesChannel->settings, 'upload_as_draft'))->toBeFalse();
});

test('bulk publish queues a publish job only for draft ebay portfolios', function () {
    Queue::fake();

    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-bulk-publish');
    $customerSalesChannel = $ebayUser->customerSalesChannel;

    CheckEbayChannel::mock()->shouldReceive('handle')->andReturn($customerSalesChannel);

    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    \App\Actions\Retina\Ebay\PublishAllRetinaEbayDraftPortfolios::make()->handle($customerSalesChannel);
    \App\Actions\Retina\Ebay\PublishRetinaEbayPortfolio::assertPushed(0);

    $portfolio->update(['data' => ['is_platform_draft' => true]]);

    \App\Actions\Retina\Ebay\PublishAllRetinaEbayDraftPortfolios::make()->handle($customerSalesChannel);
    \App\Actions\Retina\Ebay\PublishRetinaEbayPortfolio::assertPushed(1);
});

test('channel percent pricing rule prices new portfolios honestly', function () {
    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-pricing-store');
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

    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-pricing-all');
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

test('product without rrp is priced from its price instead of zero', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_no_rrp']);
    $this->product->update(['rrp' => null, 'price' => 40]);
    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    expect((float) $portfolio->customer_price)->toBe(40.0);

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, [
        'pricing_type'  => 'percent',
        'pricing_value' => 100
    ], true);

    expect((float) $portfolio->refresh()->customer_price)->toBe(80.0);
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

    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-auth-transient');
    $ebayUser->settings = ['credentials' => ['ebay_refresh_token' => 'live-token']];

    $ebayUser->refreshEbayToken();
    expect($ebayUser->ebayAuthRevoked)->toBeFalse();

    $ebayUser->refreshEbayToken();
    expect($ebayUser->ebayAuthRevoked)->toBeTrue();
});

test('a new portfolio recounts the group and organisation portfolios five minutes later, the shop one a minute later', function () {
    Queue::fake();
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();
    $channel  = StoreCustomerSalesChannel::make()->action(createOwnCustomer($this->shop, 'portfolio-hydrator-delays'), $platform, ['reference' => 'portfolio_hydrator_delays']);

    StorePortfolio::make()->action($channel, $this->product, []);

    $delayOf = fn (string $hydrator) => collect(Queue::pushedJobs())->flatten(1)->pluck('job')
        ->first(fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->getAction() instanceof $hydrator)?->delay;

    expect($delayOf(\App\Actions\SysAdmin\Group\Hydrators\GroupHydratePortfolios::class))->toBe(300)
        ->and($delayOf(\App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePortfolios::class))->toBe(300)
        ->and($delayOf(\App\Actions\Catalogue\Shop\Hydrators\ShopHydratePortfolios::class))->toBe(60);
});

test('portfolios keep no index on item name or barcode', function () {
    $indexes = collect(\Illuminate\Support\Facades\DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'portfolios'"))->pluck('indexname');

    expect($indexes)->not->toContain('portfolios_item_name_index')
        ->and($indexes)->not->toContain('portfolios_barcode_index')
        ->and($indexes)->toContain('portfolios_source_id_unique');
});

test('shop platform portfolio stats count the products of every state, each state on its own', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::TIKTOK)->firstOrFail();
    $customer = createOwnCustomer($this->shop, 'shop-platform-portfolio-stats');
    $channel  = StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'shop_platform_portfolio_stats']);

    $family     = $this->shop->productCategories()->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)->first();
    $newProduct = fn (ProductStateEnum $state) => tap(\App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 50]
    )), fn (Product $product) => \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['state' => $state->value]));

    foreach ([
        ProductStateEnum::ACTIVE,
        ProductStateEnum::ACTIVE,
        ProductStateEnum::DISCONTINUING,
        ProductStateEnum::DISCONTINUED,
    ] as $state) {
        StorePortfolio::make()->action($channel, $newProduct($state), []);
    }

    \App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydratePortfolios::run($this->shop, $platform);

    $portfoliosOfShopPlatform = fn () => \Illuminate\Support\Facades\DB::table('portfolios')
        ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
        ->where('portfolios.item_type', 'Product')
        ->where('portfolios.shop_id', $this->shop->id)
        ->where('portfolios.platform_id', $platform->id)
        ->distinct();
    $expected = ['number_products' => $portfoliosOfShopPlatform()->count('portfolios.item_id')];
    foreach (ProductStateEnum::cases() as $state) {
        $expected['number_products_state_'.$state->snake()] = $portfoliosOfShopPlatform()->where('products.state', $state->value)->count('portfolios.item_id');
    }

    $stats = $this->shop->platformStats()->where('platform_id', $platform->id)->firstOrFail()->only(array_keys($expected));

    expect($stats)->toBe($expected)
        ->and($stats['number_products_state_active'])->toBeGreaterThanOrEqual(2)
        ->and($stats['number_products_state_discontinuing'])->toBeGreaterThanOrEqual(1)
        ->and($stats['number_products_state_discontinued'])->toBeGreaterThanOrEqual(1);
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

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel::run($this->customer, [
        'items'         => [$portfolioA->id, $portfolioB->id],
        'pricing_type'  => 'percent',
        'pricing_value' => -10
    ], true);

    expect((float) $portfolioA->refresh()->customer_price)->toBe(9.0)
        ->and((float) $portfolioB->refresh()->customer_price)->toBe(18.0)
        ->and(\Illuminate\Support\Arr::get($portfolioA->settings, 'pricing_opt_out'))->toBeTrue();
});

test('bulk price rule ignores portfolios of another customer', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $this->product->update(['rrp' => 10]);

    $ownChannel   = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_bulk_price_own']);
    $ownPortfolio = StorePortfolio::make()->action($ownChannel, $this->product, []);

    $otherCustomer  = createOwnCustomer($this->shop, 'bulk price other customer');
    $otherChannel   = StoreCustomerSalesChannel::make()->action($otherCustomer, $platform, ['reference' => 'test_ebay_bulk_price_other']);
    $otherPortfolio = StorePortfolio::make()->action($otherChannel, $this->product, []);
    $otherPrice     = (float) $otherPortfolio->customer_price;

    \App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel::run($this->customer, [
        'items'         => [$ownPortfolio->id, $otherPortfolio->id],
        'pricing_type'  => 'percent',
        'pricing_value' => 50
    ], true);

    $otherPortfolio->refresh();
    expect((float) $ownPortfolio->refresh()->customer_price)->toBe(15.0)
        ->and((float) $otherPortfolio->customer_price)->toBe($otherPrice)
        ->and(\Illuminate\Support\Arr::get($otherPortfolio->settings, 'pricing'))->toBeNull()
        ->and(\Illuminate\Support\Arr::get($otherPortfolio->settings, 'pricing_opt_out'))->toBeNull();
});

test('platform portfolio logs are only reachable through the retina portfolios logs tab', function () {
    expect(\Illuminate\Support\Facades\Route::has('retina.dropshipping.customer_sales_channels.platform_portfolio_logs.index'))->toBeFalse();

    $platform             = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'test_ebay_logs']);
    $portfolio            = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);

    \App\Models\Dropshipping\PlatformPortfolioLogs::create([
        'group_id'                  => $customerSalesChannel->group_id,
        'organisation_id'           => $customerSalesChannel->organisation_id,
        'shop_id'                   => $customerSalesChannel->shop_id,
        'customer_id'               => $customerSalesChannel->customer_id,
        'customer_sales_channel_id' => $customerSalesChannel->id,
        'portfolio_id'              => $portfolio->id,
        'platform_id'               => $platform->id,
        'platform_type'             => $platform->type->value,
        'type'                      => \App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum::UPLOAD,
        'status'                    => \App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum::OK,
    ]);

    $logs = \App\Actions\Dropshipping\Portfolio\Logs\IndexPlatformPortfolioLogs::run($customerSalesChannel);

    expect($logs->total())->toBe(1)
        ->and($logs->first()->item_code)->toBe($portfolio->item_code);
});

test('exclusive products and bundles advertise real stock on sales channels', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::WOOCOMMERCE)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        ['reference' => 'test_exclusive_stock']
    );

    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($this->product, ['customer_ids' => [$this->customer->id]]);
    $this->product->update(['available_quantity' => 64]);
    $product = $this->product->refresh();

    expect($product->is_for_sale)->toBeFalse()
        ->and(UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(64)
        ->and(UpdateTiktokInventory::quantityToSend($product, $customerSalesChannel))->toBe(64)
        ->and(UpdateEbayPortfolio::quantityToSend($product, $customerSalesChannel))->toBe(64);

    UpdateProduct::make()->action($product, ['state' => ProductStateEnum::DISCONTINUED]);
    $this->product->update(['available_quantity' => 64]);
    expect(UpdateWooCustomerSalesChannelPortfolio::quantityToSend($this->product->refresh(), $customerSalesChannel))->toBe(0);
});

test('ebay portfolio check stores a published sku match in the shape the retina table expects', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'oauth2/token')) {
            return Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 7200]);
        }

        if (str_contains($url, '/inventory_item/')) {
            return Http::response(['sku' => 'abc-1', 'product' => ['title' => 'Listed On Ebay', 'imageUrls' => ['https://i.ebayimg.com/x.jpg']]]);
        }

        if (str_contains($url, '/offer')) {
            return Http::response([
                'offers' => [
                    ['offerId' => '1', 'sku' => 'abc-1', 'status' => 'UNPUBLISHED'],
                    ['offerId' => '2', 'sku' => 'abc-1', 'status' => 'PUBLISHED'],
                ],
                'total'  => 2
            ]);
        }

        return Http::response([]);
    });

    $ebayUser = storeConnectedEbayUser($this->customer, 'test-ebay-match');
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    $portfolio->update(['sku' => 'abc-1']);

    $portfolio = CheckEbayPortfolio::run($portfolio)->refresh();

    expect($portfolio->number_platform_possible_matches)->toBe(1)
        ->and($portfolio->platform_possible_matches['matches_labels'])->toBe(['Listed On Ebay'])
        ->and($portfolio->platform_possible_matches['raw_data'][0])->toBe([
            'id'     => 'abc-1',
            'name'   => 'Listed On Ebay',
            'images' => [['src' => 'https://i.ebayimg.com/x.jpg']],
        ])
        ->and($portfolio->platform_status)->toBeFalse();
});

test('dashboard shortcut reports how many open manual channels the customer has', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => 'test-ebay-only']);
    expect($ebayUser->customerSalesChannel)->not->toBeNull();

    $shortcut = GetRetinaDropshippingHomeData::run($customer->refresh())['shortcut']['order'];
    expect($shortcut['number_manual_channels'])->toBe(0)
        ->and($shortcut['manual_data'])->toBeNull();

    $manualPlatform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();
    StoreCustomerSalesChannel::make()->action($customer, $manualPlatform, ['reference' => 'test_manual_shortcut']);

    $shortcut = GetRetinaDropshippingHomeData::run($customer->refresh())['shortcut']['order'];
    expect($shortcut['number_manual_channels'])->toBe(1)
        ->and($shortcut['manual_data']['reference'])->toBe('test_manual_shortcut');
});

test('ebay wizard step one reuses an unfinished ebay user instead of minting another channel', function () {
    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $first  = StoreEbayUser::make()->handle($customer, ['name' => 'typo-name']);
    $second = StoreEbayUser::make()->handle($customer, ['name' => 'real-name']);

    expect($second->id)->toBe($first->id)
        ->and($second->name)->toBe('real-name')
        ->and($second->customerSalesChannel->name)->toBe('real-name')
        ->and($customer->customerSalesChannels()->count())->toBe(1);

    $second->update(['step' => EbayUserStepEnum::COMPLETED]);
    $third = StoreEbayUser::make()->handle($customer, ['name' => 'second-account']);

    expect($third->id)->not->toBe($first->id)
        ->and($customer->customerSalesChannels()->count())->toBe(2);
});

test('ebay callback hands the existing channel to the fresh row when the same ebay account is authorised again', function () {
    Queue::fake();
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'oauth2/token')) {
            return Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 7200]);
        }
        if (str_contains($request->url(), '/commerce/identity/')) {
            return Http::response(['userId' => 'EBAY-U1', 'username' => 'asad512-545']);
        }

        return Http::response([]);
    });
    CheckEbayChannel::mock()->shouldReceive('handle')->andReturnUsing(fn ($ebayUser) => $ebayUser->customerSalesChannel);

    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $stale = StoreEbayUser::make()->handle($customer, ['name' => 'asad512-545']);
    $stale->update(['step' => EbayUserStepEnum::COMPLETED, 'return_policy_id' => 'RP-1', 'data' => ['ebay_user' => ['userId' => 'EBAY-U1', 'username' => 'asad512-545']]]);
    $keep = $stale->customerSalesChannel;
    StorePortfolio::make()->action($keep, $this->product, []);

    $fresh  = StoreEbayUser::make()->handle($customer, ['name' => 'asad512-545']);
    $minted = $fresh->customerSalesChannel;
    expect($minted->id)->not->toBe($keep->id);

    CallbackRetinaEbayUser::make()->handle($customer, ['code' => 'abc']);

    $fresh->refresh();
    $keep->refresh();

    expect($fresh->customer_sales_channel_id)->toBe($keep->id)
        ->and($fresh->return_policy_id)->toBe('RP-1')
        ->and(Arr::get($fresh->data, 'ebay_user.userId'))->toBe('EBAY-U1')
        ->and($keep->platform_user_id)->toBe($fresh->id)
        ->and($keep->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($keep->portfolios()->count())->toBe(1)
        ->and(EbayUser::withTrashed()->find($stale->id)->trashed())->toBeTrue()
        ->and($minted->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($customer->customerSalesChannels()->where('status', CustomerSalesChannelStatusEnum::OPEN)->count())->toBe(1);
});

test('ebay callback reopens a channel closed by mistake with its products when the same ebay account is authorised again', function () {
    Queue::fake();
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'oauth2/token')) {
            return Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 7200]);
        }
        if (str_contains($request->url(), '/commerce/identity/')) {
            return Http::response(['userId' => 'EBAY-U2', 'username' => 'closed-by-mistake']);
        }

        return Http::response([]);
    });
    CheckEbayChannel::mock()->shouldReceive('handle')->andReturnUsing(fn ($ebayUser) => $ebayUser->customerSalesChannel);

    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $stale = StoreEbayUser::make()->handle($customer, ['name' => 'closed-by-mistake']);
    $stale->update(['step' => EbayUserStepEnum::COMPLETED, 'return_policy_id' => 'RP-2', 'data' => ['ebay_user' => ['userId' => 'EBAY-U2', 'username' => 'closed-by-mistake']]]);
    $keep = $stale->customerSalesChannel;
    StorePortfolio::make()->action($keep, $this->product, []);
    $family           = $this->shop->productCategories()->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)->first();
    $removedPortfolio = StorePortfolio::make()->action($keep, \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 50,
        ]
    )), []);
    Portfolio::whereKey($removedPortfolio->id)->update(['status' => false, 'updated_at' => now()->subDays(3)]);
    \App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel::run($keep->refresh());
    \App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannelPostActions::run($keep->refresh());
    \App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios::run($keep->refresh());

    $emptyExtra = StoreEbayUser::make()->handle($customer, ['name' => 'closed-by-mistake']);
    $emptyExtra->update(['step' => EbayUserStepEnum::COMPLETED, 'data' => ['ebay_user' => ['userId' => 'EBAY-U2', 'username' => 'closed-by-mistake']]]);
    $emptyChannel = $emptyExtra->customerSalesChannel;
    \App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel::run($emptyChannel->refresh());

    expect($keep->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($keep->number_portfolios)->toBe(0)
        ->and($keep->portfolios()->where('status', true)->count())->toBe(0)
        ->and(EbayUser::withTrashed()->find($stale->id)->trashed())->toBeTrue()
        ->and(EbayUser::withTrashed()->find($emptyExtra->id)->trashed())->toBeTrue();

    $fresh  = StoreEbayUser::make()->handle($customer, ['name' => 'closed-by-mistake']);
    $minted = $fresh->customerSalesChannel;

    CallbackRetinaEbayUser::make()->handle($customer, ['code' => 'abc']);

    $fresh->refresh();
    $keep->refresh();

    expect($fresh->customer_sales_channel_id)->toBe($keep->id)
        ->and($fresh->return_policy_id)->toBe('RP-2')
        ->and($keep->platform_user_id)->toBe($fresh->id)
        ->and($keep->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($keep->closed_at)->toBeNull()
        ->and($keep->name)->toBe('closed-by-mistake')
        ->and($keep->portfolios()->where('status', true)->count())->toBe(1)
        ->and($keep->number_portfolios)->toBe(1)
        ->and($removedPortfolio->refresh()->status)->toBeFalse()
        ->and($minted->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($emptyChannel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($customer->customerSalesChannels()->where('status', CustomerSalesChannelStatusEnum::OPEN)->count())->toBe(1);

    \App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannelPostActions::run($keep);

    expect($keep->portfolios()->where('status', true)->count())->toBe(1);
});

test('ebay duplicate repair folds the extra channel into the one holding most and skips channels with orders', function () {
    Queue::fake();
    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $a = StoreEbayUser::make()->handle($customer, ['name' => 'shop-a']);
    $a->update(['step' => EbayUserStepEnum::COMPLETED, 'data' => ['ebay_user' => ['userId' => 'EBAY-U9']]]);
    $b = StoreEbayUser::make()->handle($customer, ['name' => 'shop-a']);
    $b->update(['step' => EbayUserStepEnum::COMPLETED, 'data' => ['ebay_user' => ['userId' => 'EBAY-U9']]]);

    $keep  = $a->customerSalesChannel;
    $extra = $b->customerSalesChannel;
    StorePortfolio::make()->action($keep, $this->product, []);
    $family      = $this->shop->productCategories()->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY)->first();
    $makeProduct = fn () => \App\Actions\Catalogue\Product\StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 50,
        ]
    ));
    StorePortfolio::make()->action($keep, $makeProduct(), []);
    StorePortfolio::make()->action($extra, $this->product, []);
    StorePortfolio::make()->action($extra, $makeProduct(), []);
    $keep->refresh();
    $extra->refresh();

    $preview = RepairEbayDuplicateChannels::run(collect([$keep, $extra]), dryRun: true);
    expect($preview)->toHaveCount(1)
        ->and($preview[0]['action'])->toBe('would merge')
        ->and($preview[0]['portfolios'])->toBe(1)
        ->and($extra->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN);

    $keep->update(['number_orders' => 2]);
    $extra->update(['number_orders' => 1]);
    $rows = RepairEbayDuplicateChannels::run(collect([$keep->refresh(), $extra->refresh()]));
    expect($rows[0]['action'])->toBe('skipped: has orders')
        ->and($rows[0]['keep'])->toBe($keep->slug)
        ->and($extra->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN);
    $keep->update(['number_orders' => 0]);
    $extra->update(['number_orders' => 0]);

    $rows = RepairEbayDuplicateChannels::run(collect([$keep->refresh(), $extra->refresh()]));
    expect($rows[0]['action'])->toBe('merged')
        ->and($rows[0]['keep'])->toBe($keep->slug)
        ->and($keep->refresh()->portfolios()->count())->toBe(3)
        ->and($extra->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and(EbayUser::withTrashed()->find($b->id)->trashed())->toBeTrue();
});

test('iris only creates portfolios on manual channels and channels that can connect', function () {
    $this->product->update(['is_for_sale' => true, 'state' => ProductStateEnum::ACTIVE]);

    $manual = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        Platform::where('type', PlatformTypeEnum::MANUAL)->first(),
        []
    );

    $unconnected = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        Platform::where('code', 'shopify')->first(),
        []
    );
    $unconnected->update(['can_connect_to_platform' => false]);

    StoreIrisPortfolioItemsToChannels::run(
        collect([$manual->refresh(), $unconnected->refresh()]),
        [$this->product->id]
    );

    expect($manual->refresh()->portfolios()->count())->toBe(1)
        ->and($unconnected->refresh()->portfolios()->count())->toBe(0);

    $unconnected->update(['can_connect_to_platform' => true]);
    StoreIrisPortfolioItemsToChannels::run(collect([$unconnected->refresh()]), [$this->product->id]);

    expect($unconnected->refresh()->portfolios()->count())->toBe(1);
});

test('a customer client arriving from a channel is accepted with only a country', function () {
    $customerSalesChannel = $this->customer->customerSalesChannels()->first();
    expect($customerSalesChannel)->not->toBeNull();

    $definition            = CustomerClient::factory()->definition();
    $definition['address'] = array_merge($definition['address'], ['address_line_1' => '', 'address_line_2' => '', 'locality' => '', 'postal_code' => '', 'administrative_area' => '']);

    expect(StoreCustomerClient::make()->action($customerSalesChannel, $definition))->toBeInstanceOf(CustomerClient::class);
});

test('manual channel pricing policy shows an example price and says it only prices products added from now on', function () {
    $customer       = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $manualPlatform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();
    $channel        = StoreCustomerSalesChannel::make()->action($customer, $manualPlatform, ['reference' => 'test_manual_pricing_policy']);
    $channel->update(['settings' => ['pricing' => ['type' => 'percent', 'value' => 200]]]);

    $request = \Lorisleiva\Actions\ActionRequest::create('/');
    $route   = (new \Illuminate\Routing\Route('GET', '/', []))->name('retina.dropshipping.customer_sales_channels.edit')->bind($request);
    $request->setRouteResolver(fn () => $route);

    $response = \App\Actions\Retina\Platform\EditRetinaCustomerSalesChannel::make()->handle($channel->refresh(), $request);
    $props    = (new ReflectionProperty($response, 'props'))->getValue($response);
    $fields   = collect($props['formData']['blueprint'])->firstWhere(fn ($section) => isset($section['fields']['pricing_type']))['fields'];

    expect($fields['pricing_type']['type'])->toBe('pricing_policy')
        ->and($fields['pricing_type']['value'])->toBe('percent')
        ->and($fields['pricing_type']['applies_to_new_products_only'])->toBeTrue()
        ->and($fields['pricing_type']['hasOther'][0])->toBe(['name' => 'pricing_value', 'value' => 200])
        ->and($fields)->not->toHaveKey('pricing_value');
});

test('staff who can edit CRM manage a channel\'s settings from the channel page, others only see them', function () {
    $customer       = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $manualPlatform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();
    $channel        = StoreCustomerSalesChannel::make()->action($customer, $manualPlatform, ['reference' => 'test_manual_staff_settings']);
    $channel->update(['stock_update' => true, 'stock_threshold' => 3, 'settings' => ['pricing' => ['type' => 'percent', 'value' => 150]]]);
    $channel->refresh();

    $action         = \App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel::make();
    $customerFields = collect($action->getCustomerSalesChannelSettingsBlueprint($channel))->flatMap(fn ($section) => $section['fields']);

    $editable       = $action->getSettingsFormData($channel, true);
    $editableFields = collect($editable['blueprint'])->flatMap(fn ($section) => $section['fields']);

    $readOnly       = $action->getSettingsFormData($channel, false);
    $readOnlyFields = collect($readOnly['blueprint'])->flatMap(fn ($section) => $section['fields']);

    expect($editable['args']['updateRoute']['name'])->toBe('grp.models.customer_sales_channel.settings.update')
        ->and($editable['args']['updateRoute']['parameters'])->toBe(['customerSalesChannel' => $channel->id])
        ->and($editableFields->all())->toEqual($customerFields->all())
        ->and($readOnly['args']['updateRoute'])->toBeNull()
        ->and($readOnlyFields->keys()->all())->toBe($customerFields->keys()->all())
        ->and($readOnlyFields->every(fn ($field) => $field['readonly'] && $field['disabled'] && $field['noSaveButton'] && !isset($field['saveConfirmation'])))->toBeTrue()
        ->and($readOnlyFields['stock_threshold']['value'])->toBe(3)
        ->and(\App\Enums\UI\CRM\CustomerPlatformTabsEnum::navigation())->toHaveKey('settings');

    $updateUrl = route('grp.models.customer_sales_channel.settings.update', ['customerSalesChannel' => $channel->id]);

    actingAs($this->user)->patch($updateUrl, ['pricing_type' => 'fixed', 'pricing_value' => 2.5])->assertSessionHasNoErrors();
    actingAs($this->user)->patch($updateUrl, ['stock_threshold' => 7])->assertSessionHasNoErrors();
    actingAs($this->user)->patch($updateUrl, ['name' => '<b>Staff renamed</b>'])->assertSessionHasNoErrors();

    $channel->refresh();
    expect(Arr::get($channel->settings, 'pricing.type'))->toBe('fixed')
        ->and((float) Arr::get($channel->settings, 'pricing.value'))->toBe(2.5)
        ->and($channel->stock_threshold)->toBe(7)
        ->and($channel->name)->toBe('Staff renamed');

    actingAs($this->user)->patch($updateUrl, ['pricing_type' => 'not_follow'])->assertSessionHasErrors('pricing_type');

    setPermissionsTeamId($this->group->id);
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    actingAs($guest)->patch($updateUrl, ['stock_threshold' => 99])->assertForbidden();
    expect($channel->fresh()->stock_threshold)->toBe(7);

    actingAs($this->user);
});

test('a channel pricing rule that would take a price to zero or below leaves new products at their RRP', function () {
    $customer       = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $manualPlatform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();
    $channel        = StoreCustomerSalesChannel::make()->action($customer, $manualPlatform, ['reference' => 'test_manual_negative_pricing']);
    $channel->update(['settings' => ['pricing' => ['type' => 'fixed', 'value' => -50]]]);

    $this->product->update(['rrp' => 10]);

    $portfolio = StorePortfolio::make()->action($channel->refresh(), $this->product, []);

    expect((float) $portfolio->customer_price)->toBe(10.0);
});

test('letter of authorisation stays a draft until an admin uploads the signature', function () {
    $shop = UpdateShop::make()->action($this->shop, [
        'letter_of_authorisation_enabled'      => true,
        'letter_of_authorisation_company_name' => 'Ancient Wisdom Marketing Ltd',
        'letter_of_authorisation_body'         => '<p>{supplier} supplies <strong>{company}</strong> ({reference})</p>',
        'letter_of_authorisation_footer'       => '<p>Company Reg. No. 04108870</p>',
        'letter_of_authorisation_signatory'    => 'Jane Doe, Director',
    ]);
    $customer = $this->customer;

    expect(PdfCustomerLetterOfAuthorisation::isAvailable($shop))->toBeFalse();
    get(route('grp.org.shops.show.crm.customers.show.letter_of_authorisation.pdf', [$this->organisation->slug, $shop->slug, $customer->slug]))->assertNotFound();

    setPermissionsTeamId($this->group->id);
    $shopAdmin = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $shopAdmin->givePermissionTo(Permission::findByName("shop-admin.$shop->id"));
    $shopAdmin->refresh();

    actingAs($shopAdmin)->post(route('grp.models.org.shop.update', ['organisation' => $shop->organisation_id, 'shop' => $shop->id]), [
        '_method'                           => 'patch',
        'letter_of_authorisation_signature' => UploadedFile::fake()->image('signature.png', 300, 100),
    ])->assertForbidden();
    expect(PdfCustomerLetterOfAuthorisation::isSigned($shop->refresh()))->toBeFalse();

    actingAs($this->user)->post(route('grp.models.org.shop.update', ['organisation' => $shop->organisation_id, 'shop' => $shop->id]), [
        '_method'                           => 'patch',
        'letter_of_authorisation_signature' => UploadedFile::fake()->image('signature.png', 300, 100),
    ])->assertRedirect();
    $shop->refresh();
    $signatureMediaId = Arr::get($shop->settings, 'letter_of_authorisation.signature_media_id');

    expect(PdfCustomerLetterOfAuthorisation::isAvailable($shop))->toBeTrue()
        ->and($shop->getMedia('letter_of_authorisation_signature')->pluck('id')->all())->toBe([$signatureMediaId]);

    $shop = UpdateShop::make()->action($shop, [
        'letter_of_authorisation_signature' => UploadedFile::fake()->image('new-signature.png', 320, 100),
        'letter_of_authorisation_logo'      => UploadedFile::fake()->image('logo.png', 400, 100),
    ]);

    expect(Arr::get($shop->settings, 'letter_of_authorisation.signature_media_id'))->not->toBe($signatureMediaId)
        ->and($shop->getMedia('letter_of_authorisation_signature'))->toHaveCount(1)
        ->and($shop->getMedia('letter_of_authorisation_logo'))->toHaveCount(1);

    $customer->update(['company_name' => 'Bird & <Co> Ltd']);
    expect(PdfCustomerLetterOfAuthorisation::make()->body($customer->refresh()))
        ->toBe('<p>Ancient Wisdom Marketing Ltd supplies <strong>Bird &amp; &lt;Co&gt; Ltd</strong> ('.$customer->reference.')</p>');

    $response = get(route('grp.org.shops.show.crm.customers.show.letter_of_authorisation.pdf', [$this->organisation->slug, $shop->slug, $customer->slug]));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where(
            'formData.blueprint',
            fn ($blueprint) => collect($blueprint)->contains(fn ($section) => isset($section['fields']['letter_of_authorisation_signature']))
        ));
});

test('customer downloads the signed letter of authorisation from account settings', function () {
    $website = createWebsite($this->shop);
    if ($website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($website);
    }
    $customer = createCustomer($this->shop);
    $customer->update(['status' => CustomerStatusEnum::APPROVED]);
    $webUser = createWebUser($customer);

    DetectWebsiteFromDomain::mock()->shouldReceive('handle')->with('localhost')->andReturn($website);
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Retina')]);

    $shop = $webUser->customer->shop;
    UpdateShop::make()->action($shop, ['letter_of_authorisation_enabled' => false]);

    actingAs($webUser, 'retina');
    $this->get(route('retina.sysadmin.letter_of_authorisation.pdf'))->assertForbidden();
    $this->get(route('retina.sysadmin.settings.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions', [])
            ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->every(fn ($section) => !isset($section['fields']['is_gift_opted_out']))));

    UpdateShop::make()->action($shop, ['letter_of_authorisation_enabled' => true]);
    $this->get(route('retina.sysadmin.letter_of_authorisation.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('retina.sysadmin.settings.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0.route.name', 'retina.sysadmin.letter_of_authorisation.pdf'));
});

test('packaging preferences list only active packagings and never preselect a retired one the customer had saved', function () {
    $website = createWebsite($this->shop);
    if ($website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($website);
    }
    $customer = createCustomer($this->shop);
    $customer->update(['status' => CustomerStatusEnum::APPROVED]);
    $webUser = createWebUser($customer);

    DetectWebsiteFromDomain::mock()->shouldReceive('handle')->with('localhost')->andReturn($website);
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Retina')]);

    $shop = $website->shop;
    $shop->update(['settings' => array_replace_recursive($shop->settings ?? [], ['packaging_and_inserts' => ['enabled' => true]])]);

    $suffix  = strtoupper(Str::random(6));
    $store   = fn (string $family, \App\Enums\Catalogue\Packaging\PackagingStateEnum $state) => \App\Actions\Billables\Packaging\StorePackaging::make()->action($shop, [
        'code'        => $family.'-'.$suffix,
        'family_code' => $family.$suffix,
        'name'        => $family.' box',
        'type'        => \App\Enums\Catalogue\Packaging\PackagingTypeEnum::STANDARD->value,
        'price'       => 1,
        'state'       => $state->value,
    ]);
    $active  = $store('LIVEPK', \App\Enums\Catalogue\Packaging\PackagingStateEnum::ACTIVE);
    $retired = $store('OLDPK', \App\Enums\Catalogue\Packaging\PackagingStateEnum::DISCONTINUED);
    \App\Actions\Billables\Leaflet\StoreLeaflet::make()->action($shop, ['name' => 'Card '.$suffix, 'type' => \App\Enums\Catalogue\Leaflet\LeafletTypeEnum::THANK_YOU_CARD->value, 'price' => 1]);

    \App\Models\CRM\CustomerHasPackaging::create(['customer_id' => $customer->id, 'packaging_id' => $retired->id, 'personalised_message' => 'Thank you for shopping with us', 'data' => []]);

    actingAs($webUser, 'retina');
    $this->get(route('retina.sysadmin.packaging-preferences.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SysAdmin/RetinaPackagingPreferences')
            ->where('packagingOptions', fn ($options) => collect($options)->pluck('family_code')->contains($active->family_code)
                && !collect($options)->pluck('family_code')->contains($retired->family_code))
            ->where('selectedFamilyCode', null)
            ->where('personalisedMessage', 'Thank you for shopping with us')
            ->where('offersPersonalisedMessage', true));
});

test('a new order takes the customer saved personalised message, and an order message already set is kept', function () {
    $shop = $this->shop;
    $shop->update(['settings' => array_replace_recursive($shop->settings ?? [], ['packaging_and_inserts' => ['enabled' => true]])]);

    $suffix    = strtoupper(Str::random(6));
    $packaging = \App\Actions\Billables\Packaging\StorePackaging::make()->action($shop, [
        'code'        => 'MSGPK-'.$suffix,
        'family_code' => 'MSGPK'.$suffix,
        'name'        => 'Message box',
        'type'        => \App\Enums\Catalogue\Packaging\PackagingTypeEnum::STANDARD->value,
        'price'       => 1,
        'state'       => \App\Enums\Catalogue\Packaging\PackagingStateEnum::ACTIVE->value,
    ]);

    \App\Actions\Billables\Leaflet\StoreLeaflet::make()->action($shop, ['name' => 'Card '.$suffix, 'type' => \App\Enums\Catalogue\Leaflet\LeafletTypeEnum::THANK_YOU_CARD->value, 'price' => 1]);

    $customer = StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
    \App\Models\CRM\CustomerHasPackaging::create(['customer_id' => $customer->id, 'packaging_id' => $packaging->id, 'personalised_message' => 'Thank you for shopping with us', 'data' => []]);

    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer, [
        'date'             => date('Y-m-d'),
        'customer_id'      => $customer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);

    expect($order->refresh()->personalised_message)->toBe('Thank you for shopping with us')
        ->and($order->packaging_id)->toBe($packaging->id);

    $order->update(['personalised_message' => 'Order message', 'insert_types' => null]);
    \App\Actions\Ordering\Order\ApplyDefaultOrderPackaging::run($order);

    expect($order->refresh()->personalised_message)->toBe('Order message');
});

test('a shop with every insert switched off takes no personalised message and queues none for printing', function () {
    $shop = $this->shop;
    $shop->update(['settings' => array_replace_recursive($shop->settings ?? [], ['packaging_and_inserts' => ['enabled' => true]])]);
    \App\Models\Billables\Leaflet::where('shop_id', $shop->id)->update(['state' => \App\Enums\Catalogue\Leaflet\LeafletStateEnum::INACTIVE]);

    $suffix    = strtoupper(Str::random(6));
    $packaging = \App\Actions\Billables\Packaging\StorePackaging::make()->action($shop, [
        'code'        => 'OFFPK-'.$suffix,
        'family_code' => 'OFFPK'.$suffix,
        'name'        => 'Plain box',
        'type'        => \App\Enums\Catalogue\Packaging\PackagingTypeEnum::STANDARD->value,
        'price'       => 1,
        'state'       => \App\Enums\Catalogue\Packaging\PackagingStateEnum::ACTIVE->value,
    ]);
    \App\Actions\Billables\Leaflet\StoreLeaflet::make()->action($shop, ['name' => 'Card '.$suffix, 'type' => \App\Enums\Catalogue\Leaflet\LeafletTypeEnum::THANK_YOU_CARD->value, 'price' => 1, 'state' => \App\Enums\Catalogue\Leaflet\LeafletStateEnum::INACTIVE->value]);

    $customer = StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
    \App\Models\CRM\CustomerHasPackaging::create(['customer_id' => $customer->id, 'packaging_id' => $packaging->id, 'personalised_message' => 'Saved before inserts were switched off', 'data' => []]);

    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer, [
        'date'             => date('Y-m-d'),
        'customer_id'      => $customer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);

    \App\Actions\Ordering\Order\UpdateOrderPackaging::make()->action($order, ['packaging_id' => $packaging->id, 'leaflet_ids' => [], 'personalised_message' => 'Typed in the basket']);
    expect($shop->offersPersonalisedMessage())->toBeFalse()
        ->and($order->refresh()->personalised_message)->toBeNull()
        ->and(\App\Actions\Retina\Dropshipping\Orders\GetRetinaOrderPackagingData::run($shop, $customer, $order)['offersPersonalisedMessage'])->toBeFalse();

    $order->updateQuietly(['personalised_message' => 'Left from before the switch']);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote::make()->action($order->refresh(), [
        'reference'        => 'OFF'.$suffix,
        'state'            => \App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'warehouse_id'     => createWarehouse()->id,
    ]);

    expect(\App\Models\Dispatching\DeliveryNoteLeaflet::where('delivery_note_id', $deliveryNote->id)->count())->toBe(0);
});

test('an ebay match that crashes marks its upload log failed instead of leaving it processing', function () {
    Http::fake(fn ($request) => str_contains($request->url(), 'oauth2/token')
        ? Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 7200])
        : Http::response([]));

    $ebayUser  = storeConnectedEbayUser($this->customer, 'test-ebay-match-crash');
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    CheckEbayPortfolio::shouldRun()->andThrow(new RuntimeException('eBay match crashed'));

    expect(fn () => \App\Actions\Dropshipping\Ebay\Product\MatchPortfolioToCurrentEbayProduct::run($portfolio, ['platform_product_id' => 'abc-1']))
        ->toThrow(RuntimeException::class, 'eBay match crashed');

    $log = \App\Models\Dropshipping\PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->latest('id')->first();
    expect($log->status)->toBe(\App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum::FAIL)
        ->and($log->response)->toContain('eBay match crashed');
});

test('reset channel only resets shopify channels', function () {
    $ebayChannel = storeConnectedEbayUser($this->customer, 'test-ebay-reset')->customerSalesChannel;
    \App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteAllFulfilmentServices::shouldNotRun();

    actingAs($this->user);
    $this->patch(route('grp.models.customer_sales_channel.shopify_reset', ['customerSalesChannel' => $ebayChannel->id]))
        ->assertSessionHasErrors('channel');
});

test('staff match and push portfolios to a platform only with crm edit on the channel shop, INI-076', function () {
    $unguarded = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'grp.models.') && is_string($route->getAction('uses')))
        ->map(fn ($route) => \Illuminate\Support\Str::before($route->getAction('uses'), '@'))
        ->filter(fn (string $action) => str_starts_with($action, 'App\\Actions\\Dropshipping\\') && !method_exists($action, 'authorize'))
        ->unique()->values()->all();
    expect($unguarded)->toBe([]);

    $platform             = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'ini076_ebay']);
    $portfolio            = StorePortfolio::make()->action($customerSalesChannel, $this->product, []);
    $shop                 = $customerSalesChannel->shop;

    $originalRoles       = $this->user->roles->pluck('name')->toArray();
    $originalPermissions = $this->user->getDirectPermissions()->pluck('name')->all();

    try {
        actingAsUserWithOnlyPermissions($this->user, ["crm.$shop->id.view", "orders.$shop->id.edit"]);
        $this->post(route('grp.models.portfolio.match_to_existing_ebay_product', $portfolio->id), ['platform_product_id' => 'x'])->assertForbidden();
        $this->post(route('grp.models.portfolio.store_new_ebay_product', $portfolio->id))->assertForbidden();
        $this->post(route('grp.models.ebay.batch_match', $customerSalesChannel->id), [])->assertForbidden();
        $this->post(route('grp.models.shopify.batch_match', $customerSalesChannel->id), [])->assertForbidden();
        $this->post(route('grp.models.woo.revive', $customerSalesChannel->id))->assertForbidden();
        $this->post(route('grp.models.woo.batch_upload', $customerSalesChannel->id), ['portfolios' => [$portfolio->id]])->assertForbidden();
        $this->post(route('grp.models.shopify.batch_upload', $customerSalesChannel->id), [])->assertForbidden();
        $this->post(route('grp.models.portfolio.match_to_existing_shopify_product', $portfolio->id), [])->assertForbidden();

        actingAsUserWithOnlyPermissions($this->user, ["crm.$shop->id.edit"]);
        $this->post(route('grp.models.portfolio.match_to_existing_ebay_product', $portfolio->id), [])->assertSessionHasErrors('platform_product_id');
    } finally {
        setPermissionsTeamId($this->user->group_id);
        $this->user->syncPermissions($originalPermissions);
        actingAsUserWithRoles($this->user, $originalRoles);
    }
});

test('staff without crm or accounting rights cannot list a channel marketplace products', function () {
    $platform             = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first();
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'json_auth_ebay_'.uniqid()]);
    $shop                 = $customerSalesChannel->shop;

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);

    $this->getJson(route('grp.json.dropshipping.customer_sales_channel.ebay_products', $customerSalesChannel->id))->assertForbidden();
});

function storeActiveProductsInFamily(Product $product, int $number): \Illuminate\Support\Collection
{
    $tradeUnit = $product->tradeUnits()->first();

    return collect(range(1, $number))->map(fn () => UpdateProduct::make()->action(
        StoreProduct::make()->action($product->family, array_merge(Product::factory()->definition(), [
            'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 1]],
            'price'       => 10,
        ])),
        ['state' => ProductStateEnum::ACTIVE]
    ));
}

test('adding products to a channel reads the same rows whether it adds one product or five', function () {
    Queue::fake();
    $products = storeActiveProductsInFamily($this->product, 6);
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();
    $channel  = StoreCustomerSalesChannel::make()->action(createOwnCustomer($this->shop, 'portfolio-batch-queries'), $platform, ['reference' => 'portfolio_batch_queries']);

    $queriesToAdd = function (array $productIds) use ($channel): array {
        $channel = CustomerSalesChannel::find($channel->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        StoreMultiplePortfolios::make()->action($channel, ['items' => $productIds]);
        DB::disableQueryLog();

        return collect(DB::getQueryLog())->pluck('query')->all();
    };

    $addingOne  = $queriesToAdd([$products[0]->id]);
    $addingFive = $queriesToAdd($products->slice(1)->pluck('id')->all());

    $isInsert = fn (string $sql) => str_starts_with(strtolower($sql), 'insert');

    expect(count(array_filter($addingFive, fn ($sql) => !$isInsert($sql))))->toBe(count(array_filter($addingOne, fn ($sql) => !$isInsert($sql))))
        ->and(count(array_filter($addingFive, $isInsert)))->toBe(5 * count(array_filter($addingOne, $isInsert)))
        ->and($channel->portfolios()->whereIn('item_id', $products->pluck('id'))->where('status', true)->count())->toBe(6)
        ->and($channel->refresh()->number_portfolios)->toBe(6)
        ->and(Portfolio::where('customer_sales_channel_id', $channel->id)->whereDoesntHave('stats')->count())->toBe(0);
});

test('a product sent twice in one add is added once instead of failing the whole add', function () {
    Queue::fake();
    $products = storeActiveProductsInFamily($this->product, 2);
    $channel  = storeConnectedEbayUser($this->customer, 'test-ebay-duplicate-add')->customerSalesChannel;
    $itemIds  = $products->pluck('id')->all();

    DB::transaction(fn () => StoreMultiplePortfolios::make()->action($channel, ['items' => [...$itemIds, $itemIds[0]]]));

    expect($channel->portfolios()->whereIn('item_id', $itemIds)->count())->toBe(2);
});

test('products added to a platform channel are checked in the platform by a queued job, not in the request', function () {
    Queue::fake();
    $products = storeActiveProductsInFamily($this->product, 2);
    $channel  = storeConnectedEbayUser($this->customer, 'test-ebay-batch-check')->customerSalesChannel;

    CheckEbayPortfolio::mock()->shouldNotReceive('handle');

    StoreMultiplePortfolios::make()->action($channel, ['items' => $products->pluck('id')->all()]);

    $newPortfolioIds = $channel->portfolios()->whereIn('item_id', $products->pluck('id'))->orderBy('id')->pluck('id')->all();

    Queue::assertPushedOn('ds', JobDecorator::class, fn (JobDecorator $job) => $job->getAction() instanceof CheckNewPortfoliosInPlatform
        && $job->getParameters()[1] === $newPortfolioIds);
});

test('the queued platform check checks each new portfolio and tells the customer when it is done', function () {
    Event::fake([CloneRetinaPortfolioProgressEvent::class]);
    $products = storeActiveProductsInFamily($this->product, 2);
    $website = createWebsite($this->shop);
    if ($website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($website);
    }
    $customer = createOwnCustomer($this->shop, 'portfolio-batch-check-job');
    $webUser  = StoreWebUser::make()->action($customer, ['username' => 'checker-'.Str::random(6), 'email' => Str::random(6).'@testmail.com', 'password' => 'test']);
    $channel  = storeConnectedEbayUser($customer, 'test-ebay-batch-check-job')->customerSalesChannel;

    $portfolios = $products->map(fn (Product $product) => StorePortfolio::make()->action($channel, $product, [], checkInPlatform: false));

    $checked = [];
    CheckEbayPortfolio::mock()->shouldReceive('handle')->andReturnUsing(function (Portfolio $portfolio) use (&$checked) {
        $checked[] = $portfolio->id;

        return $portfolio;
    });

    CheckNewPortfoliosInPlatform::run($channel, $portfolios->pluck('id')->all());

    expect($checked)->toBe($portfolios->pluck('id')->all());

    Event::assertDispatchedTimes(CloneRetinaPortfolioProgressEvent::class, 1);
    Event::assertDispatched(CloneRetinaPortfolioProgressEvent::class, fn (CloneRetinaPortfolioProgressEvent $event) => $event->userId === $webUser->id && $event->actionType === CheckNewPortfoliosInPlatform::PROGRESS_ACTION_TYPE
        && $event->actionId === $channel->id && $event->total === 2 && $event->done === 2 && $event->numberFails === 0);
});
