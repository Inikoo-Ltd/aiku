<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 16:30:57 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\CRM\Customer\AttachCustomerToPlatform;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Actions\SysAdmin\Group\CreateAccessToken;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Helpers\ImgProxy\Image;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformStats;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    \Tests\Helpers\setupDropshippingTest($this);
});

test('test platform were seeded ', function () {
    expect($this->group->platforms()->count())->toBe(4);
    $platform = Platform::first();
    expect($platform)->toBeInstanceOf(Platform::class)
        ->and($platform->stats)->toBeInstanceOf(PlatformStats::class);

    $this->artisan('group:seed-platforms')->assertExitCode(0);
    expect($this->group->platforms()->count())->toBe(4);
});

test('add platform to customer', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();


    expect($this->customer->platforms->count())->toBe(0)
        ->and($this->customer->getMainPlatform())->toBeNull();
    $customer = AttachCustomerToPlatform::make()->action(
        $this->customer,
        $platform,
        [
            'reference' => 'test_shopify_reference'
        ]
    );


    $customer->refresh();


    expect($customer->platforms->first())->toBeInstanceOf(Platform::class)
        ->and($customer->getMainPlatform())->toBeInstanceOf(Platform::class)
        ->and($customer->getMainPlatform()->type)->toBe(PlatformTypeEnum::SHOPIFY);


    return $customer;
});

test('create customer client', function () {
    $platform = $this->customer->getMainPlatform();

    $customerClient = StoreCustomerClient::make()->action(
        $this->customer,
        array_merge(CustomerClient::factory()->definition(), [
            'platform_id' => $platform->id,
        ])
    );

    expect($customerClient)->toBeInstanceOf(CustomerClient::class);

    return $customerClient;
});

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
})->depends('create customer client');

test('add product to customer portfolio', function () {
    $platform = $this->customer->platforms()->first();
    expect($platform)->toBeInstanceOf(Platform::class);
    $dropshippingCustomerPortfolio = StorePortfolio::make()->action(
        $this->customer,
        $this->product,
        [
            'platform_id' => $platform->id,
        ]
    );
    expect($dropshippingCustomerPortfolio)->toBeInstanceOf(Portfolio::class);

    return $dropshippingCustomerPortfolio;
});


test('add image to product', function () {
    Storage::fake('public');

    expect($this->product)->toBeInstanceOf(Product::class)
        ->and($this->product->images->count())->toBe(0);

    $fakeImage = UploadedFile::fake()->image('hello.jpg');
    $path      = $fakeImage->store('photos', 'public');

    SaveModelImages::run(
        $this->product,
        [
            'path'         => Storage::disk('public')->path($path),
            'originalName' => $fakeImage->getClientOriginalName()

        ],
        'photo',
        'product_images'
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
        $this->product,
        [
            'path'         => Storage::disk('public')->path($path2),
            'originalName' => $fakeImage2->getClientOriginalName()
        ],
        'photo',
        'product_images'
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

    expect($imageSources1)->toBeArray()->toHaveCount(3);
})->depends('add 2nd image to product');

test('get product 2nd images and show resized sources', function () {
    $media2 = $this->product->images->last();
    expect($media2)->toBeInstanceOf(Media::class);


    $image2 = $media2->getImage()->resize(5, 5);
    expect($image2)->toBeInstanceOf(Image::class);

    $imageSources2 = GetPictureSources::run($image2);
    expect($imageSources2)->toBeArray()->toHaveCount(6);
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
})->skip();

test('UI Index customer clients', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer            = $customerClient->customer;
    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.manual.index', [
        $customerClient->organisation->slug,
        $customerClient->shop->slug,
        $customerClient->customer->slug,
        $customerHasPlatform->platform->slug,
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
                    ->where('title', $customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->depends('create customer client');


test('UI Show customer client', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();

    $customer            = $customerClient->customer;
    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.show', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerHasPlatform->platform->slug,
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
    $customer            = $customerClient->customer;
    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();

    $response = get(route('grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.create', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerHasPlatform->platform->slug,
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 6);
    });
})->depends('create customer client');

test('UI edit customer client', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer            = $customerClient->customer;
    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();

    $response = get(route('grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.edit', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerHasPlatform->platform->slug,
        $customerClient->ulid
    ]));
    $response->assertInertia(function (AssertableInertia $page) use ($customerClient) {
        $page
            ->component('EditModel')
            ->where('title', 'edit client')
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint', 1)
                    ->where('blueprint.0.title', 'contact')
                    ->has('blueprint.0.fields.company_name')
                    ->where('blueprint.0.fields.company_name.label', 'company')
                    ->where('blueprint.0.fields.company_name.value', $customerClient->company_name)
                    ->has('blueprint.0.fields.contact_name')
                    ->where('blueprint.0.fields.contact_name.label', 'contact name')
                    ->where('blueprint.0.fields.contact_name.value', $customerClient->contact_name)
                    ->has('blueprint.0.fields.email')
                    ->where('blueprint.0.fields.email.label', 'email')
                    ->where('blueprint.0.fields.email.value', $customerClient->email)
                    ->has('blueprint.0.fields.phone')
                    ->where('blueprint.0.fields.phone.label', 'phone')
                    ->where('blueprint.0.fields.phone.value', $customerClient->phone)
                    ->etc()
            )
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'edit client')
                    ->has('actions')
                    ->etc()
            )
            ->has('breadcrumbs', 6);
    });
})->depends('create customer client');

test('UI Index customer portfolios', function () {
    $customer = Customer::first();
    $response = $this->get(
        route(
            'grp.org.shops.show.crm.customers.show.portfolios.index',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $customer->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/Portfolios')
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
});

test('UI get section route client dropshipping', function (CustomerClient $customerClient) {
    $customer = $customerClient->customer;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.manual.index', [
        'organisation' => $customer->organisation->slug,
        'shop'         => $customer->shop->slug,
        'customer'     => $customer->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::DROPSHIPPING->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
})->depends('create customer client');


test('UI index customer client order', function () {
    $this->withoutExceptionHandling();
    $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

    $customer = AttachCustomerToPlatform::make()->action(
        $this->customer,
        $platform,
        []
    );

    $customerClient = StoreCustomerClient::make()->action(
        $this->customer,
        array_merge(CustomerClient::factory()->definition(), [
            'platform_id' => $platform->id,
        ])
    );

    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();
    $response            = $this->get(route('grp.org.shops.show.crm.customers.show.platforms.show.orders.index', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerHasPlatform->platform->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/CustomerPlatformOrders')
            ->has('title')
            ->has('breadcrumbs', 5)
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
});

test('UI index customer client portfolios', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer = $customerClient->customer;

    $customerHasPlatform = $customer->customerHasPlatforms()->where('platform_id', $customerClient->platform_id)->first();
    $response            = $this->get(route('grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,
        $customerHasPlatform->platform->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/Portfolios')
            ->has('title')
            ->has('breadcrumbs', 5)
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

test('UI index customer platforms', function (CustomerClient $customerClient) {
    $this->withoutExceptionHandling();
    $customer = $customerClient->customer;

    $response = $this->get(route('grp.org.shops.show.crm.customers.show.platforms.index', [
        $customer->organisation->slug,
        $customer->shop->slug,
        $customer->slug,

    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Shop/CRM/PlatformsInCustomer')
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
    CustomerClientHydrateBasket::run($customerClient);
    expect($customerClient)->toBeInstanceOf(CustomerClient::class)
        ->and($customerClient->amount_in_basket)->toEqual(0)
        ->and($customerClient->current_order_in_basket_id)->toBeNull();
});

test('Dropshipping hydrators', function () {
    $this->artisan('hydrate', [
        '--sections' => 'dropshipping',
    ])->assertExitCode(0);
});
