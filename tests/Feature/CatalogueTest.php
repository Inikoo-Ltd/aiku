<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 22:04:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Charge\UpdateCharge;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Billables\Service\UpdateService;
use App\Actions\Catalogue\Collection\AttachModelsToCollection;
use App\Actions\Catalogue\Collection\DetachModelFromCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use Illuminate\Support\Facades\DB;
use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\Product\DeleteProduct;
use App\Actions\Catalogue\Product\HydrateProducts;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateFirstAvailableAt;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\StoreProductVariant;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\AttachFamiliesToSubDepartment;
use App\Actions\Catalogue\ProductCategory\DetachFamilyToSubDepartment;
use App\Actions\Catalogue\ProductCategory\HydrateDepartments;
use App\Actions\Catalogue\ProductCategory\HydrateFamilies;
use App\Actions\Catalogue\ProductCategory\HydrateSubDepartments;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Catalogue\Shop\HydrateShops;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Catalogue\Product\RetireProductIntoReplacement;
use App\Actions\Catalogue\Product\KeepRetiredProductAsSeparate;
use App\Actions\Catalogue\Product\UI\EditProduct;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Maintenance\Catalogue\RepairProductsFirstAvailableAt;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Models\Accounting\Invoice;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Billables\Charge;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

uses()->group('base');

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->guest        = createAdminGuest($this->organisation->group);
    $this->warehouse    = createWarehouse();
    $this->adminGuest   = createAdminGuest($this->organisation->group);
    $this->group        = $this->organisation->group;

    $stocks          = createStocks($this->group);
    $orgStocks       = createOrgStocks($this->organisation, $stocks);
    $this->orgStock1 = $orgStocks[0];
    $this->orgStock2 = $orgStocks[1];

    $tradeUnits       = createTradeUnits($this->group);
    $this->tradeUnit1 = $tradeUnits[0];
    $this->tradeUnit2 = $tradeUnits[1];


    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
    setPermissionsTeamId($this->organisation->group->id);
});

test('create shop', function () {
    $organisation = $this->organisation;
    $storeData    = Shop::factory()->definition();
    data_set($storeData, 'type', ShopTypeEnum::B2B->value);
    $shop = StoreShop::make()->action($this->organisation, $storeData);
    $organisation->refresh();

    $shopRoles       = Role::where('scope_type', 'Shop')->where('scope_id', $shop->id)->get();
    $shopPermissions = Permission::where('scope_type', 'Shop')->where('scope_id', $shop->id)->get();

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->is_aiku)->toBeTrue()
        ->and(Shop::find($shop->id)->send_chat_ai_suggestions)->toBeFalse()
        ->and($organisation->group->catalogueStats->number_shops)->toBe(1)
        ->and($organisation->group->catalogueStats->number_shops_type_b2b)->toBe(1)
        ->and($organisation->catalogueStats->number_shops)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_type_b2b)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_state_in_process)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_state_open)->toBe(0)
        ->and($shopRoles->pluck('name')->all())->toEqualCanonicalizing(RolesEnum::getRolesWithScope($shop))
        ->and($shopPermissions->pluck('name')->all())->toEqualCanonicalizing(ShopPermissionsEnum::getAllValues($shop));


    $user = $this->guest->getUser();
    $user->refresh();

    expect($user->hasAllRoles(["shop-admin-$shop->id"]))->toBeTrue();


    return $shop;
});

test('create shop website', function (Shop $shop) {
    $website = StoreWebsite::make()->action(
        $shop,
        Website::factory()->definition(),
    );
    $shop->refresh();

    expect($website)->toBeInstanceOf(Website::class)
        ->and($shop->website->id)->toBe($website->id);

    return $shop;
})->depends('create shop');

test('create shop by command', function () {
    $organisation = $this->organisation;
    $this->artisan('shop:create', [
        'organisation' => $organisation->slug,
        'name'         => 'Test Shop',
        'code'         => 'TEST',
        'type'         => ShopTypeEnum::FULFILMENT->value,
        '--warehouses' => [$this->warehouse->id]
    ])->assertExitCode(0);
    $organisation->refresh();

    expect($organisation->catalogueStats->number_shops)->toBe(2)
        ->and($organisation->catalogueStats->number_shops_type_b2b)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_type_fulfilment)->toBe(1);
})->depends('create shop');

test('update shop', function (Shop $shop) {
    expect($shop->state)->toBe(ShopStateEnum::IN_PROCESS)
        ->and($shop->organisation->catalogueStats->number_shops_state_in_process)->toBe(2);
    $updateData = [
        'name'  => 'Test Shop Updated',
        'state' => ShopStateEnum::OPEN
    ];

    $shop = UpdateShop::make()->action($shop, $updateData);
    $shop->refresh();

    expect($shop->name)->toBe('Test Shop Updated')
        ->and($shop->group->catalogueStats->number_shops)->toBe(2)
        ->and($shop->group->catalogueStats->number_shops_state_in_process)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_shops)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_shops_state_in_process)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_shops_state_open)->toBe(1);
})->depends('create shop');

test('update shop settings creates audit with dotted key', function (Shop $shop) {
    $shop = UpdateShop::make()->action($shop, ['reviews' => true]);

    $audit = $shop->audits()->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values)->toHaveKey('settings.reviews.enabled');
})->depends('create shop');

test('seed shop permissions from command', function () {
    $this->artisan('shop:seed-permissions')->assertExitCode(0);
})->depends('create shop by command');


test('create department', function ($shop) {
    $departmentData = ProductCategory::factory()->definition();
    data_set($departmentData, 'type', ProductCategoryTypeEnum::DEPARTMENT->value);

    $department = StoreProductCategory::make()->action($shop, $departmentData);
    expect($department)->toBeInstanceOf(ProductCategory::class)
        ->and($department->state)->toBe(ProductCategoryStateEnum::IN_PROCESS)
        ->and($department->type)->toBe(ProductCategoryTypeEnum::DEPARTMENT)
        ->and($department->group->catalogueStats->number_departments)->toBe(1)
        ->and($department->group->catalogueStats->number_departments_state_in_process)->toBe(1)
        ->and($department->organisation->catalogueStats->number_departments)->toBe(1)
        ->and($department->organisation->catalogueStats->number_departments_state_in_process)->toBe(1)
        ->and($department->shop->stats->number_departments)->toBe(1)
        ->and($department->shop->stats->number_departments_state_in_process)->toBe(1);


    return $department;
})->depends('create shop');

test('create product category webpage', function (ProductCategory $department) {
    $webpage = StoreProductCategoryWebpage::make()->action($department);
    $department->refresh();

    expect($webpage)->toBeInstanceOf(Webpage::class)
        ->and($webpage->model_type)->toBe('ProductCategory')
        ->and(intval($webpage->model_id))->toBe($department->id)
        ->and($department->webpage->id)->toBe($webpage->id);

    UpdateProductCategory::make()->action($department, ['url' => 'a-nice-url']);
    $department->refresh();
    expect($department->webpage->id)->toBe($webpage->id);

    return $department;
})->depends('create department');

test('create sub department', function ($productCategory) {
    $subDepartmentData = ProductCategory::factory()->definition();
    data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
    $subDepartment = StoreProductCategory::make()->action($productCategory, $subDepartmentData);
    expect($subDepartment)->toBeInstanceOf(ProductCategory::class)
        ->and($subDepartment->type)->toBe(ProductCategoryTypeEnum::SUB_DEPARTMENT)
        ->and($subDepartment->group->catalogueStats->number_departments)->toBe(1)
        ->and($subDepartment->group->catalogueStats->number_sub_departments)->toBe(1)
        ->and($subDepartment->organisation->catalogueStats->number_departments)->toBe(1)
        ->and($subDepartment->organisation->catalogueStats->number_sub_departments)->toBe(1)
        ->and($subDepartment->shop->stats->number_departments)->toBe(1)
        ->and($subDepartment->shop->stats->number_sub_departments)->toBe(1)
        ->and($subDepartment->department->stats->number_sub_departments)->toBe(1);

    return $subDepartment;
})->depends('create department');

test('iris sub department listing sends the whole catalogue in the first page', function (ProductCategory $subDepartment) {
    expect(GetIrisProductsInProductCategory::run($subDepartment)->perPage())->toBe(200)
        ->and(GetIrisProductsInProductCategory::run($subDepartment->department)->perPage())->toBe(20);
})->depends('create sub department');

test('create second department', function ($shop) {
    $departmentData = ProductCategory::factory()->definition();
    data_set($departmentData, 'type', ProductCategoryTypeEnum::DEPARTMENT->value);

    $department = StoreProductCategory::make()->action($shop, $departmentData);
    expect($department)->toBeInstanceOf(ProductCategory::class)
        ->and($department->group->catalogueStats->number_departments)->toBe(2);

    return $department;
})->depends('create shop');

test('update department', function ($department) {
    $newName    = 'Updated Department Name';
    $department = UpdateProductCategory::make()->action(
        $department,
        [
            'name' => $newName
        ]
    );

    expect($department->name)->toBe($newName);

    return $department;
})->depends('create department');

test('create family', function ($department) {
    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);

    $family = StoreProductCategory::make()->action($department, $familyData);
    $department->refresh();


    expect($family)->toBeInstanceOf(ProductCategory::class)
        ->and($family->type)->toBe(ProductCategoryTypeEnum::FAMILY)
        ->and($family->group->catalogueStats->number_families)->toBe(1)
        ->and($family->organisation->catalogueStats->number_families)->toBe(1)
        ->and($family->shop->stats->number_families)->toBe(1)
        ->and($family->department)->toBeInstanceOf(ProductCategory::class)
        ->and($family->department->id)->toBe($department->id)
        ->and($department->stats->number_families)->toBe(1)
        ->and($department->stats->number_current_families)->toBe(0);

    return $family;
})->depends('update department');


test('attach and detach family to sub department', function (ProductCategory $family, ProductCategory $subDepartment) {
    AttachFamiliesToSubDepartment::make()->action($subDepartment, ['families_id' => [$family->id]]);
    $family->refresh();

    expect($family->sub_department_id)->toBe($subDepartment->id)
        ->and($family->parent_id)->toBe($subDepartment->id)
        ->and($family->department_id)->toBe($subDepartment->department_id);

    DetachFamilyToSubDepartment::make()->handle($family);
    $family->refresh();

    expect($family->sub_department_id)->toBeNull()
        ->and($family->department_id)->toBe($subDepartment->department_id)
        ->and($family->parent_id)->toBe($subDepartment->department_id);
})->depends('create family', 'create sub department');


test('create product', function (ProductCategory $family) {
    $tradeUnits = [
        [
            'id'       => $this->tradeUnit1->id,
            'quantity' => 1,
        ]
    ];

    $productData = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => $tradeUnits,
            'price'       => 100,
            'unit'        => 'unit'
        ]
    );

    $product = StoreProduct::make()->action($family, $productData);
    $product->refresh();

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->family)->toBeInstanceOf(ProductCategory::class)
        ->and($product->family->department)->toBeInstanceOf(ProductCategory::class)
        ->and($product->state)->toBe(ProductStateEnum::ACTIVE)
        ->and($product->asset)->toBeInstanceOf(Asset::class)
        ->and($product->historicAsset)->toBeInstanceOf(HistoricAsset::class)
        ->and($product->tradeUnits()->count())->toBe(1)
        ->and($product->organisation->catalogueStats->number_products)->toBe(1)
        ->and($product->organisation->catalogueStats->number_current_products)->toBe(1)
        ->and($product->organisation->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($product->organisation->catalogueStats->number_assets_type_service)->toBe(0)
        ->and($product->group->catalogueStats->number_products)->toBe(1)
        ->and($product->group->catalogueStats->number_current_products)->toBe(1)
        ->and($product->group->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($family->department->stats->number_products)->toBe(1)
        ->and($family->department->stats->number_products_state_in_process)->toBe(0)
        ->and($family->department->stats->number_current_products)->toBe(1)
        ->and($family->stats->number_products)->toBe(1)
        ->and($family->stats->number_current_products)->toBe(1)
        ->and($product->department)->toBeInstanceOf(ProductCategory::class)
        ->and($product->department->stats->number_products)->toBe(1)
        ->and($product->department->stats->number_current_products)->toBe(1)
        ->and($product->shop->stats->number_assets_type_product)->toBe(1)
        ->and($product->stats->number_product_variants)->toBe(1);


    return $product;
})->depends('create family');




test('create product with many org stocks', function ($shop) {
    $tradeUnits = [
        [
            'id'       => $this->tradeUnit1->id,
            'quantity' => 1,
        ],
        [
            'id'       => $this->tradeUnit2->id,
            'quantity' => 1,
        ]
    ];


    $productData = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => $tradeUnits,
            'price'       => 99,
            'unit'        => 'pack'
        ]
    );


    $product = StoreProduct::make()->action($shop, $productData);
    $shop->refresh();

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->tradeUnits()->count())->toBe(2)
        ->and($shop->stats->number_products)->toBe(2)
        ->and($product->asset->stats->number_historic_assets)->toBe(1)
        ->and($shop->group->catalogueStats->number_products)->toBe(2)
        ->and($shop->group->catalogueStats->number_assets_type_product)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_products)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_assets_type_product)->toBe(2);

    return $product;
})->depends('create family');

test('closing shop discontinues its active products', function (Product $product) {
    $shop = $product->shop;
    if ($product->state != \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE) {
        \App\Actions\Catalogue\Product\UpdateProduct::make()->action($product, ['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]);
    }

    UpdateShop::make()->action($shop, ['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::CLOSED]);

    expect($product->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED);

    UpdateShop::make()->action($shop, ['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);
    \App\Actions\Catalogue\Product\UpdateProduct::make()->action($product, ['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]);

    expect($product->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE);
})->depends('create product');

test('update product', function (Product $product) {
    expect($product->name)->not->toBe('Updated Asset Name')
        ->and($product->asset->stats->number_historic_assets)->toBe(1);
    $productData = [
        'name'        => 'Updated Asset Name',
        'description' => 'Updated Asset Description',
        'rrp'         => 99.99
    ];
    $product     = UpdateProduct::make()->action($product, $productData);
    $product->refresh();
    /** @var Asset $asset */
    $asset = $product->asset;

    expect($product->name)->toBe('Updated Asset Name')
        ->and($product->asset->stats->number_historic_assets)->toBe(2)
        ->and($asset->stats->number_historic_assets)->toBe(2)
        ->and($asset->name)->toBe('Updated Asset Name')
        ->and($product->name)->toBe('Updated Asset Name');

    return $product;
})->depends('create product');

test('hydrate available quantity recovers stale not-for-sale status', function (Product $product) {
    $product->updateQuietly(['status' => ProductStatusEnum::NOT_FOR_SALE, 'is_for_sale' => true]);

    $product = ProductHydrateAvailableQuantity::run($product->refresh());
    expect($product->status)->not->toBe(ProductStatusEnum::NOT_FOR_SALE);

    $product->updateQuietly(['is_for_sale' => false]);
    $product = ProductHydrateAvailableQuantity::run($product->refresh());
    expect($product->status)->toBe(ProductStatusEnum::NOT_FOR_SALE);

    $product->updateQuietly(['is_for_sale' => true]);
    $product = ProductHydrateAvailableQuantity::run($product->refresh());
    expect($product->status)->not->toBe(ProductStatusEnum::NOT_FOR_SALE);
})->depends('update product');

test('update product trade units recalculates units', function (Product $product) {
    expect((float) $product->units)->toBe(1.0);

    $product = UpdateProduct::make()->action($product, [
        'trade_units' => [
            [
                'id'       => $this->tradeUnit1->id,
                'quantity' => 9,
            ]
        ],
    ]);
    $product->refresh();

    expect($product->tradeUnits->first()->pivot->quantity)->toBe('9.00000000')
        ->and((float) $product->units)->toBe(9.0);

    $product = UpdateProduct::make()->action($product, [
        'trade_units' => [
            [
                'id'       => $this->tradeUnit1->id,
                'quantity' => 1,
            ]
        ],
    ]);
    $product->refresh();
    expect((float) $product->units)->toBe(1.0);

    return $product;
})->depends('update product');

test('add variant to product', function (Product $product) {
    expect($product->stats->number_product_variants)->toBe(1);

    $productVariant = StoreProductVariant::run(
        $product,
        [
            'code'    => $product->code.'-v1',
            'ratio'   => 2,
            'price'   => 99,
            'name'    => $product->name.' variant 1',
            'is_main' => false
        ]
    );
    $product->refresh();


    expect($productVariant)->toBeInstanceOf(Product::class)
        ->and($productVariant->asset)->toBeInstanceOf(Asset::class)
        ->and($productVariant->is_main)->toBeFalse()
        ->and($productVariant->mainProduct->id)->toBe($product->id)
        ->and($product->stats->number_product_variants)->toBe(2)
        ->and($product->asset->stats->number_historic_assets)->toBe(4);


    return $productVariant;
})
    ->depends('update product');

test('update second product variant', function (Product $productVariant) {
    /** @var Product $product */
    $product = $productVariant->mainProduct;
    expect($product->stats->number_product_variants)->toBe(2);
    $modelData = [
        'name'  => 'Updated Product Sec Name',
        'code'  => 'sec_code',
        'price' => 99.99
    ];

    $productVariant = UpdateProduct::make()->action($productVariant, $modelData);
    $productVariant->refresh();
    $product->refresh();

    expect($productVariant->name)->toBe('Updated Product Sec Name')
        ->and($productVariant->code)->toBe('sec_code')
        ->and($product->stats->number_product_variants)->toBe(2);

    return $product;
})->depends('add variant to product');

test('store product webpage', function (Product $product) {
    $webpage = StoreProductWebpage::make()->action($product);
    $product->refresh();

    // expect($product->webpage)->not->toBeNull();

    expect($webpage)->toBeInstanceOf(Webpage::class)
        ->and($webpage->model_type)->toBe('Product')
        ->and(intval($webpage->model_id))->toBe($product->id);

    return $webpage;
})->depends('update second product variant');

test('delete product', function ($product) {
    $shop = $product->shop;


    expect($shop->stats->number_products)->toBe(2)
        ->and($product->stats->number_product_variants)->toBe(2)
        ->and($shop->group->catalogueStats->number_assets)->toBe(4)
        ->and($shop->group->catalogueStats->number_products)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_products)->toBe(2);

    DeleteProduct::run($product);
    $shop->refresh();

    expect($shop->stats->number_products)->toBe(1)
        ->and($shop->group->catalogueStats->number_assets)->toBe(3)
        ->and($shop->group->catalogueStats->number_products)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_products)->toBe(1);

    return $shop;
})->depends('create product');

test('create service', function (Shop $shop) {
    $serviceData = array_merge(
        Service::factory()->definition(),
        [
            'price' => 100,
            'unit'  => 'job',
        ]
    );

    $service = StoreService::make()->action($shop, $serviceData);
    $shop->refresh();
    $group        = $shop->group;
    $organisation = $shop->organisation;
    $asset        = $service->asset;

    expect($service)->toBeInstanceOf(Service::class)
        ->and($service->state)->toBe(ServiceStateEnum::ACTIVE)
        ->and($service->status)->toBeTrue()
        ->and($asset)->toBeInstanceOf(Asset::class)
        ->and($service->asset->stats->number_historic_assets)->toBe(1)
        ->and($group->catalogueStats->number_assets)->toBe(4)
        ->and($group->catalogueStats->number_products)->toBe(1)
        ->and($group->catalogueStats->number_services)->toBe(1)
        ->and($group->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($group->catalogueStats->number_assets_type_service)->toBe(1)
        ->and($organisation->catalogueStats->number_products)->toBe(1)
        ->and($organisation->catalogueStats->number_assets_type_service)->toBe(1)
        ->and($shop->stats->number_assets)->toBe(3)
        ->and($shop->stats->number_products)->toBe(1)
        ->and($shop->stats->number_assets_type_product)->toBe(1)
        ->and($shop->stats->number_assets_type_service)->toBe(1);

    return $service;
})->depends('delete product');

test('update service', function (Service $service) {
    expect($service->name)->not->toBe('Updated Service Name');
    $productData = [
        'name'        => 'Updated Service Name',
        'description' => 'Updated Service Description',
        'rrp'         => 99.99
    ];
    $service     = UpdateService::make()->action(service: $service, modelData: $productData);

    $service->refresh();

    expect($service->asset->name)->toBe('Updated Service Name')
        ->and($service->asset->stats->number_historic_assets)->toBe(2)
        ->and($service->asset->stats->number_historic_assets)->toBe(2);

    $service = UpdateService::make()->action($service, ['state' => ServiceStateEnum::DISCONTINUED->value]);
    expect($service->state)->toBe(ServiceStateEnum::DISCONTINUED)
        ->and($service->status)->toBeFalse();

    $service = UpdateService::make()->action($service, ['state' => ServiceStateEnum::ACTIVE->value]);
    expect($service->state)->toBe(ServiceStateEnum::ACTIVE)
        ->and($service->status)->toBeTrue();

    return $service;
})->depends('create service');


test('create collection', function ($shop) {
    $collection = StoreCollection::make()->action(
        $shop,
        [
            'code'        => 'MyFColl',
            'name'        => 'My first collection',
            'description' => 'My first collection description'
        ]
    );
    $shop->refresh();
    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($shop->stats->number_collections)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_collections)->toBe(1)
        ->and($shop->group->catalogueStats->number_collections)->toBe(1);


    return $collection;
})->depends('create shop');


test('update collection', function ($collection) {
    expect($collection->name)->not->toBe('Updated Collection Name');

    $collectionData = [
        'code'        => 'updated-code',
        'name'        => 'Updated Collection Name',
        'description' => 'Updated Collection Description',
    ];
    $collection     = UpdateCollection::make()->action($collection, $collectionData);

    expect($collection->name)->toBe('Updated Collection Name')
        ->and($collection->code)->toBe('updated-code');

    return $collection;
})->depends('create collection');


test('create charge', function ($shop) {
    $charge = StoreCharge::make()->action(
        $shop,
        [
            'code'        => 'MyFColl',
            'name'        => 'My first charge',
            'type'        => ChargeTypeEnum::HANGING,
            'trigger'     => ChargeTriggerEnum::ORDER,
            'description' => 'Charge description',
            'price'       => fake()->numberBetween(100, 2000),
            'unit'        => 'charge',
        ]
    );
    $shop->refresh();
    expect($charge)->toBeInstanceOf(Charge::class)
        ->and($shop->stats->number_assets_type_charge)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_assets_type_charge)->toBe(3)
        ->and($shop->group->catalogueStats->number_assets_type_charge)->toBe(3);


    return $charge;
})->depends('create shop');

test('update charge', function ($charge) {
    $updatedCharge = UpdateCharge::make()->action(
        $charge,
        [
            'code'  => 'MyFColl2',
            'name'  => 'Charge1',
            'price' => fake()->numberBetween(100, 2000),
            'unit'  => 'charge',
            'state' => ChargeStateEnum::ACTIVE
        ]
    );
    $updatedCharge->refresh();
    expect($updatedCharge)->toBeInstanceOf(Charge::class)
        ->and($updatedCharge->name)->toBe('Charge1')
        ->and($updatedCharge->state)->toBe(ChargeStateEnum::ACTIVE)
        ->and($updatedCharge->status)->toBeTrue()
        ->and($updatedCharge->shop->stats->number_assets_type_charge)->toBe(2)
        ->and($updatedCharge->organisation->catalogueStats->number_assets_type_charge)->toBe(3)
        ->and($updatedCharge->group->catalogueStats->number_assets_type_charge)->toBe(3);


    return $updatedCharge;
})->depends('create charge');

test('add items to collection', function (Collection $collection) {
    $data = [
        'families' => [4],
        'products' => [2]
    ];

    $collection = AttachModelsToCollection::make()->action($collection, $data);
    $collection->refresh();
    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->stats->number_families)->toBe(1);

    return $collection;
})->depends('update collection');

test('remove items to collection', function (Collection $collection) {
    /** @var ProductCategory $family */
    $family = ProductCategory::find(4);

    $collection = DetachModelFromCollection::make()->action(
        $collection,
        $family
    );
    $collection->refresh();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->stats->number_families)->toBe(0);
})->depends('add items to collection');

test('hydrate shops', function (Shop $shop) {
    HydrateShops::run($shop);
    $this->artisan('hydrate:shops')->assertExitCode(0);
})->depends('create shop');

test('hydrate departments', function (ProductCategory $department) {
    HydrateDepartments::run($department);
    $this->artisan('hydrate:departments')->assertExitCode(0);
})->depends('create department');

test('hydrate sub-departments', function (ProductCategory $department) {
    HydrateSubDepartments::run($department);
    $this->artisan('hydrate:sub_departments')->assertExitCode(0);
})->depends('create sub department');

test('hydrate families', function (ProductCategory $family) {
    HydrateFamilies::run($family);
    $this->artisan('hydrate:families')->assertExitCode(0);
})->depends('create family');

test('hydrate products', function () {
    HydrateProducts::run(Product::first());
    $this->artisan('hydrate:products')->assertExitCode(0);
});


test('can show catalogue', function (Shop $shop) {
    $response = get(route('grp.org.shops.show.catalogue.dashboard', [
        $shop->organisation->slug,
        $shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Catalogue')
            ->has('breadcrumbs', 3);
    });
})->depends('create shop');

test('update shop setting', function ($shop) {
    $c = Country::first();
    $l = Language::first();

    $modelData = [
        'company_name' => 'new company name',
        'code'         => "NEW",
        'name'         => "new_name",
        'type'         => ShopTypeEnum::DROPSHIPPING,
        'country_id'   => $c->id,
        'language_id'  => $l->id,
        'email'        => "test@gmail.com",
        'phone'        => "08912312313"

    ];
    $shop      = UpdateShop::make()->action($shop, $modelData);
    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->company_name)->toBe('new company name')
        ->and($shop->code)->toBe('NEW')
        ->and($shop->name)->toBe('new_name')
        ->and($shop->type)->toBe(ShopTypeEnum::DROPSHIPPING)
        ->and($shop->country_id)->toBe($c->id)
        ->and($shop->language_id)->toBe($l->id)
        ->and($shop->email)->toBe('test@gmail.com')
        ->and($shop->phone)->toBe('08912312313');
})->depends('create shop');


test('catalogue hydrator', function () {
    $this->artisan('hydrate -s cat')->assertExitCode(0);
});

test('billables hydrator', function () {
    $this->artisan('hydrate -s bil')->assertExitCode(0);
});

test('a product can be priced at zero, free gifts are not editable otherwise', function (ProductCategory $family) {
    $product = StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]],
            'price'       => 100,
            'unit'        => 'unit',
        ]
    ));

    // The gold reward gifts, bottle caps and tea samples are all priced at zero
    $product = UpdateProduct::make()->action($product, ['price' => 0]);

    expect((float)$product->refresh()->price)->toBe(0.0);
})->depends('create family');

test('a Wix order with a product missing from the catalogue is skipped and reported once, not on every poll', function (ProductCategory $family) {
    $action   = \App\Actions\Catalogue\Shop\External\Wix\StoreOrderFromWix::make();
    $wixOrder = [
        'id'        => 'wix-order-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
        'status'    => 'APPROVED',
        'number'    => '20189',
        'lineItems' => [['id' => 'line-1', 'physicalProperties' => ['sku' => 'NOT-IN-CATALOGUE-'.\Illuminate\Support\Str::random(6)], 'quantity' => 1]],
    ];
    $reportKey = $action->skippedOrderReportKey($family->shop, $wixOrder['id']);

    expect(\Illuminate\Support\Facades\Cache::has($reportKey))->toBeFalse()
        ->and($action->handle($family->shop, $wixOrder))->toBeNull()
        ->and(\Illuminate\Support\Facades\Cache::has($reportKey))->toBeTrue()
        ->and($action->handle($family->shop, $wixOrder))->toBeNull();
})->depends('create family');

test('a Wix order keeps the sale price and shipping the buyer paid in Wix', function (ProductCategory $family) {
    $product = StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 53]
    ));

    $order = \App\Actions\Catalogue\Shop\External\Wix\StoreOrderFromWix::make()->handle($family->shop, [
        'id'           => 'wix-order-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
        'status'       => 'APPROVED',
        'number'       => '20181',
        'createdDate'  => now()->toIso8601String(),
        'buyerInfo'    => ['email' => 'wix-buyer-'.\Illuminate\Support\Str::random(6).'@example.com'],
        'billingInfo'  => ['contactDetails' => ['firstName' => 'Wix', 'lastName' => 'Buyer'], 'address' => ['country' => 'GB', 'city' => 'Chesterfield', 'postalCode' => 'S41 9QJ', 'addressLine' => 'Broombank Road']],
        'shippingInfo' => ['cost' => ['price' => ['amount' => '7.50'], 'totalPriceBeforeTax' => ['amount' => '7.50']]],
        'priceSummary' => ['subtotal' => ['amount' => '45.05'], 'shipping' => ['amount' => '7.50'], 'tax' => ['amount' => '10.50']],
        'lineItems'    => [[
            'id'                   => 'line-1',
            'quantity'             => 1,
            'physicalProperties'   => ['sku' => $product->code],
            'price'                => ['amount' => '45.05'],
            'priceBeforeDiscounts' => ['amount' => '53.00'],
            'totalPriceBeforeTax'  => ['amount' => '45.05'],
            'totalPriceAfterTax'   => ['amount' => '54.06'],
        ]],
    ]);

    expect($order)->not->toBeNull()
        ->and((float) $order->goods_amount)->toBe(45.05)
        ->and((float) $order->gross_amount)->toBe(53.0)
        ->and((float) $order->shipping_amount)->toBe(7.5)
        ->and((float) $order->net_amount)->toBe(52.55)
        ->and((float) $order->tax_amount)->toBe(10.5)
        ->and((float) $order->total_amount)->toBe(63.05);

    $order->transactions()->where('model_type', 'Product')->first()->update(['net_amount' => 20]);
    \App\Actions\Ordering\Order\CalculateOrderTotalAmounts::run($order->refresh(), calculateDiscounts: false);

    expect((float) $order->refresh()->net_amount)->toBe(27.5)
        ->and((float) $order->tax_amount)->not->toBe(10.5);
})->depends('create family');

test('a free Wix variant syncs onto an existing product without an rrp', function (ProductCategory $family) {
    $product = StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10, 'rrp' => 12]
    ));

    $synced = \App\Actions\Catalogue\Shop\External\Wix\GetWixProducts::make()->upsertWixProduct($product->shop, [
        'product_id'  => 'wix-free-gift',
        'variant_id'  => 'wix-free-gift-variant',
        'sku'         => $product->code,
        'name'        => 'Free gift',
        'description' => null,
        'price'       => 0.0,
        'image'       => null,
        'visible'     => true,
    ]);

    expect($synced?->id)->toBe($product->id)
        ->and((float) $synced->price)->toBe(0.0)
        ->and($synced->rrp)->toBeNull();
})->depends('create family');

test('wix products without variants share the all-zero variant id and still import as separate products', function () {
    $wixShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::WIX->value,
    ]));
    $action           = \App\Actions\Catalogue\Shop\External\Wix\GetWixProducts::make();
    $defaultVariantId = \App\Actions\Catalogue\Shop\External\Wix\GetWixProducts::WIX_V1_DEFAULT_VARIANT_ID;
    $wixVariant       = fn (string $wixProductId, string $sku) => [
        'product_id'  => $wixProductId,
        'variant_id'  => $defaultVariantId,
        'sku'         => $sku,
        'name'        => "Soap $sku",
        'description' => null,
        'price'       => 45.58,
        'image'       => null,
        'visible'     => true,
    ];

    $suffix   = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
    $products = collect(range(1, 5))->map(fn (int $i) => $action->upsertWixProduct($wixShop, $wixVariant("wix-product-$suffix-$i", "RAWM-$suffix-$i")));

    expect($products->filter()->pluck('id')->unique())->toHaveCount(5)
        ->and($products->map(fn (Product $product) => $product->refresh()->marketplace_id)->unique()->all())->toBe([$defaultVariantId])
        ->and($products[0]->code)->toBe("RAWM-$suffix-1")
        ->and($products[0]->marketplace_second_id)->toBe("wix-product-$suffix-1")
        ->and($products[4]->code)->toBe("RAWM-$suffix-5");

    $resynced = $action->upsertWixProduct($wixShop, $wixVariant("wix-product-$suffix-2", "RAWM-$suffix-2"));

    expect($resynced->id)->toBe($products[1]->id)
        ->and($products[0]->refresh()->code)->toBe("RAWM-$suffix-1");

    $products->each(fn (Product $product) => $product->updateQuietly(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]));
    $liveVariantKeys = $products->take(4)
        ->mapWithKeys(fn (Product $product) => [$action->getWixVariantKey($product->marketplace_second_id, $product->marketplace_id) => true])
        ->all();

    expect($action->discontinueProductsGoneFromWix($wixShop, $liveVariantKeys))->toBe(1)
        ->and($products[4]->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED)
        ->and($products[0]->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE);
});

test('stock changes of several variants of one wix product queue a single inventory push', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $variant = fn (int $id, string $wixProductId) => (new Product())->forceFill(['id' => $id, 'shop_id' => 900001, 'marketplace_second_id' => $wixProductId]);
    $wixProductId = 'wix-product-'.uniqid();

    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::dispatch($variant(1, $wixProductId));
    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::dispatch($variant(2, $wixProductId));
    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::dispatch($variant(3, $wixProductId.'-other'));

    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::assertPushed(2);

    $this->travel(61)->seconds();
    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::dispatch($variant(4, $wixProductId));

    \App\Actions\Catalogue\Shop\External\Wix\UpdateWixProductInventoryQuantity::assertPushed(3);
});

test('a product can be exclusive to several customers and only they can see it', function () {
    list($organisation, $user, $shop) = createShop();

    // createCustomer() returns the shop's first customer, so it cannot give three distinct ones
    $newCustomer = fn () => \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );

    $customerA = $newCustomer();
    $customerB = $newCustomer();
    $customerC = $newCustomer();

    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $visibleTo = fn (?int $customerId) => \App\Models\Catalogue\Product::where('shop_id', $shop->id)
        ->visibleToCustomer($customerId)
        ->whereKey($product->id)
        ->exists();

    expect($product->exclusive_for_customer_id)->toBeNull()
        ->and($visibleTo(null))->toBeTrue()
        ->and($visibleTo($customerC->id))->toBeTrue();

    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, [
        'customer_ids' => [$customerA->id, $customerB->id],
    ]);
    $product->refresh();

    expect($product->exclusiveCustomers()->count())->toBe(2)
        ->and($product->exclusive_for_customer_id)->toBe($customerA->id)
        ->and($product->isExclusive())->toBeTrue()
        ->and($visibleTo($customerA->id))->toBeTrue()
        ->and($visibleTo($customerB->id))->toBeTrue()
        ->and($visibleTo($customerC->id))->toBeFalse()
        ->and($visibleTo(null))->toBeFalse();

    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, [
        'customer_ids' => [],
    ]);
    $product->refresh();

    expect($product->exclusiveCustomers()->count())->toBe(0)
        ->and($product->exclusive_for_customer_id)->toBeNull()
        ->and($product->isExclusive())->toBeFalse()
        ->and($visibleTo(null))->toBeTrue();
});

test('a private product off the website can still be put on its own customer order', function () {
    list($organisation, $user, $shop) = createShop();

    $owner = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );
    $other = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );

    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, [
        'customer_ids' => [$owner->id],
    ]);
    $product->update(['is_for_sale' => false]);

    $sellableTo = fn (?int $customerId) => \App\Models\Catalogue\Product::where('shop_id', $shop->id)
        ->sellableToCustomer($customerId)
        ->whereKey($product->id)
        ->exists();

    expect($sellableTo($owner->id))->toBeTrue()
        ->and($sellableTo($other->id))->toBeFalse()
        ->and($sellableTo(null))->toBeFalse();
});

test('a product made for one customer is recorded as theirs', function () {
    list($organisation, $user, $shop) = createShop();

    $newCustomer = fn () => \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );
    $owner = $newCustomer();
    $other = $newCustomer();

    [, $seedProduct] = createProduct($shop);
    $product = StoreProduct::make()->action($seedProduct->family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units'               => [['id' => $seedProduct->tradeUnits->first()->id, 'quantity' => 1]],
            'price'                     => 1,
            'exclusive_for_customer_id' => $owner->id,
        ]
    ));

    expect($product->exclusiveCustomers()->pluck('customers.id')->all())->toBe([$owner->id])
        ->and(Product::whereKey($product->id)->visibleToCustomer($other->id)->exists())->toBeFalse()
        ->and($owner->refresh()->number_exclusive_products)->toBe(1);
});

test('staff choose who a product is sold to from its edit page', function () {
    list($organisation, $user, $shop) = createShop();

    $newCustomer = fn () => \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );
    $owner      = $newCustomer();
    $wrongOwner = $newCustomer();

    [, $seedProduct] = createProduct($shop);
    $product = StoreProduct::make()->action($seedProduct->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $seedProduct->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 1]
    ));
    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, ['customer_ids' => [$wrongOwner->id]]);

    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($owner, \App\Models\Ordering\Order::factory()->definition());
    \App\Actions\Ordering\Transaction\StoreTransaction::make()->action($order, $product->historicAsset, ['quantity_ordered' => 1]);

    $soldOnlyTo = fn () => collect(EditProduct::make()->getBlueprint($product->refresh()))->pluck('fields')->collapse();

    expect($soldOnlyTo()->get('customer_ids')['value'])->toBe([$wrongOwner->id])
        ->and($soldOnlyTo()->get('customer_ids')['information_warning'][0]['description'])->toContain($owner->reference)
        ->and($soldOnlyTo()->has('is_for_sale'))->toBeFalse();

    UpdateProduct::make()->action($product, ['exclusive_for_customer_id' => $owner->id]);
    expect($product->refresh()->exclusive_for_customer_id)->toBe($wrongOwner->id);

    patch(route('grp.models.product.exclusive_customers.update', $product->id), ['customer_ids' => [$owner->id]])
        ->assertSessionHasNoErrors();
    $product->refresh();

    expect($product->exclusiveCustomers()->pluck('customers.id')->all())->toBe([$owner->id])
        ->and($product->exclusive_for_customer_id)->toBe($owner->id)
        ->and($product->is_for_sale)->toBeFalse()
        ->and($owner->refresh()->number_exclusive_products)->toBe(1)
        ->and($wrongOwner->refresh()->number_exclusive_products)->toBe(0)
        ->and($soldOnlyTo()->get('customer_ids')['information_warning'])->toBe([]);

    expect(fn () => StoreProductWebpage::make()->action($product))->toThrow(\Illuminate\Validation\ValidationException::class);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [$shop->organisation->slug, $shop->slug, $product->slug]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('exclusive_customers.0.reference', $owner->reference)
                ->where('pageHead.actions', fn ($actions) => collect($actions)->doesntContain('label', __('Create Webpage')))
        );

    patch(route('grp.models.product.exclusive_customers.update', $product->id), ['customer_ids' => []])
        ->assertSessionHasNoErrors();

    expect($product->refresh()->exclusive_for_customer_id)->toBeNull()
        ->and($product->isExclusive())->toBeFalse();
});

test('repair repoints products from a discontinued org stock to its active twin', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $product = UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $activeOrgStock = $this->orgStock1;
    $activeOrgStock->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::ACTIVE, 'code' => 'REPAIR-01']);

    $brokenOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        $activeOrgStock->stock,
        array_merge(\App\Models\Inventory\OrgStock::factory()->definition(), ['code' => 'REPAIR-01-error']),
    );
    $brokenOrgStock->update(['state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED]);

    $product->orgStocks()->sync([$brokenOrgStock->id => ['quantity' => 1]]);

    $repaired = \App\Actions\Catalogue\Product\RepairProductsLinkedToDiscontinuedOrgStock::make()
        ->handle($this->organisation->id);

    expect($repaired)->toBeGreaterThanOrEqual(1)
        ->and($product->orgStocks()->pluck('org_stocks.id')->all())->toBe([$activeOrgStock->id]);
});

test('product ingredients and origin stop reflecting a trade unit once it is removed', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $lamp = $this->tradeUnit1;
    $lamp->update(['marketing_ingredients' => 'Coconut Leaf, Albasia Wood, Cotton', 'country_of_origin' => 'IDN']);

    $fitting = $this->tradeUnit2;
    $fitting->update(['marketing_ingredients' => 'Glass', 'country_of_origin' => 'CHN']);

    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($product, [
        ['id' => $lamp->id, 'quantity' => 1],
        ['id' => $fitting->id, 'quantity' => 1],
    ]);
    $product->refresh();

    expect($product->country_of_origin)->toContain('CHN')
        ->and($product->country_of_origin)->toContain('IDN');

    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($product, [
        ['id' => $lamp->id, 'quantity' => 1],
    ]);
    $product->refresh();

    expect($product->marketing_ingredients)->toBe('Coconut Leaf, Albasia Wood, Cotton')
        ->and($product->country_of_origin)->toBe('IDN');

    $lamp->update(['country_of_origin' => null]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateHeathAndSafetyFromTradeUnits::run(Product::find($product->id));
    $product->refresh();

    expect($product->country_of_origin)->toBeNull();
});

test('a multi part product declares only its main part to customs', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $lamp = $this->tradeUnit1;
    $lamp->update(['tariff_code' => '9405990090', 'country_of_origin' => 'PAK']);
    $base = $this->tradeUnit2;
    $base->update(['tariff_code' => '4421999999', 'country_of_origin' => 'CHN']);

    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($product, [
        ['id' => $lamp->id, 'quantity' => 1],
        ['id' => $base->id, 'quantity' => 1],
    ]);
    $product = Product::find($product->id);
    $product->update(['customs_trade_unit_id' => null]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateHeathAndSafetyFromTradeUnits::run($product);

    expect($product->refresh()->tariff_code)->toContain(',');

    $product = UpdateProduct::make()->action($product, ['customs_trade_unit_id' => $lamp->id]);

    expect($product->refresh()->tariff_code)->toBe($lamp->getTariffCodeForOrganisation($product->organisation_id))
        ->and($product->country_of_origin)->toBe('PAK');

    $product = UpdateProduct::make()->action($product, ['customs_trade_unit_id' => null]);

    expect($product->refresh()->country_of_origin)->toContain('CHN')
        ->and($product->country_of_origin)->toContain('PAK');

    $outsider = \App\Models\Goods\TradeUnit::whereNotIn('id', [$lamp->id, $base->id])->first();
    if ($outsider) {
        expect(fn () => UpdateProduct::make()->action($product, ['customs_trade_unit_id' => $outsider->id]))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    }
});

test('repair command resyncs product ingredients and origin from trade units', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $this->tradeUnit1->update(['marketing_ingredients' => 'Coconut Leaf', 'country_of_origin' => 'IDN']);
    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($product, [
        ['id' => $this->tradeUnit1->id, 'quantity' => 1],
    ]);
    \App\Actions\Catalogue\Product\RepairProductIngredientsAndOriginFromTradeUnits::make()->handle($shop->id);

    Product::where('id', $product->id)->update([
        'marketing_ingredients' => 'Glass',
        'country_of_origin'     => 'CHN, IDN',
    ]);

    $dryRun = \App\Actions\Catalogue\Product\RepairProductIngredientsAndOriginFromTradeUnits::make()
        ->handle($shop->id, true);

    expect($dryRun['stale'])->toBe(1)
        ->and(Product::find($product->id)->marketing_ingredients)->toBe('Glass');

    $repaired = \App\Actions\Catalogue\Product\RepairProductIngredientsAndOriginFromTradeUnits::make()
        ->handle($shop->id);

    expect($repaired['stale'])->toBe(1);

    $product = Product::find($product->id);
    expect($product->marketing_ingredients)->toBe('Coconut Leaf')
        ->and($product->country_of_origin)->toBe('IDN');
});

test('a bundle combines its trade unit ingredients and shows no component dimensions', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $bulb = $this->tradeUnit1;
    $bulb->update(['marketing_ingredients' => 'Cotton, Spare Bulbs', 'marketing_dimensions' => ['width' => 5]]);

    $lamp = $this->tradeUnit2;
    $lamp->update(['marketing_ingredients' => 'Himalayan Salt, Cotton', 'marketing_dimensions' => ['width' => 20]]);

    \App\Actions\Catalogue\Product\SyncProductTradeUnits::run($product, [
        ['id' => $bulb->id, 'quantity' => 1],
        ['id' => $lamp->id, 'quantity' => 1],
    ]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingIngredientsFromTradeUnits::run(Product::find($product->id));
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits::run(Product::find($product->id));

    $product = Product::find($product->id);
    expect(explode(', ', $product->marketing_ingredients))
        ->toHaveCount(3)
        ->toContain('Cotton', 'Spare Bulbs', 'Himalayan Salt')
        ->and($product->marketing_dimensions)->toBeEmpty();

    Product::where('id', $product->id)->update(['marketing_dimensions' => json_encode(['width' => 5])]);
    $product = Product::find($product->id);

    expect(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits::make()->cameFromOneOfSeveralTradeUnits($product))->toBeTrue();

    \App\Actions\Catalogue\Product\RepairProductIngredientsAndOriginFromTradeUnits::make()->handle($shop->id);

    expect(Product::find($product->id)->marketing_dimensions)->toBeNull();
});

test('a starter spec block lists its components with a link to each one sold on its own', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createWebsite($shop);

    $storeTradeUnit = fn (string $ingredients) => \App\Actions\Goods\TradeUnit\StoreTradeUnit::make()->action(
        $this->group,
        array_merge(\App\Models\Goods\TradeUnit::factory()->definition(), ['marketing_ingredients' => $ingredients])
    );
    $lotion = $storeTradeUnit('Aqua, Glycerin');
    $stand  = $storeTradeUnit('Recycled Teak');

    $storeProduct = fn (array $tradeUnits) => StoreProduct::make()->action($shop, array_merge(
        Product::factory()->definition(),
        ['trade_units' => $tradeUnits, 'price' => 10]
    ));

    $lotionProduct = $storeProduct([['id' => $lotion->id, 'quantity' => 4]]);
    $lotionWebpage = StoreProductWebpage::make()->action($lotionProduct);
    $lotionWebpage->update(['state' => WebpageStateEnum::LIVE]);
    DB::table('products')->where('id', $lotionProduct->id)->update(['state' => ProductStateEnum::ACTIVE->value, 'is_single_trade_unit' => true]);

    $starter = $storeProduct([
        ['id' => $lotion->id, 'quantity' => 4],
        ['id' => $stand->id, 'quantity' => 1],
    ]);

    $request = \Lorisleiva\Actions\ActionRequest::createFrom(request());
    $detail  = \App\Actions\Iris\Catalogue\GetProductDetail::make()->jsonResponse(Product::find($starter->id), $request);

    $components = collect($detail['components'])->keyBy('code');

    expect($detail['components'])->toHaveCount(2)
        ->and($detail['specifications']['ingredients'])->toBeNull()
        ->and($detail['specifications']['dimensions'])->toBeNull()
        ->and($detail['specifications']['cpnp'])->toBeNull()
        ->and($components[$lotionProduct->code]['name'])->toBe($lotionProduct->name)
        ->and($components[$lotionProduct->code]['quantity'])->toBe(4.0)
        ->and($components[$lotionProduct->code]['url'])->toEndWith('/'.$lotionWebpage->url)
        ->and($components[$lotionProduct->code]['ingredients'])->toBeNull()
        ->and($components[$stand->code]['name'])->toBe($stand->name)
        ->and($components[$stand->code]['url'])->toBeNull()
        ->and($components[$stand->code]['ingredients'])->toBe('Recycled Teak');

    $blockSpecifications = \App\Http\Resources\Web\WebBlockProductResource::make(Product::find($starter->id))->toArray($request)['specifications'];

    expect($blockSpecifications['ingredients'])->toBeNull()
        ->and($blockSpecifications['cpnp'])->toBeNull();

    $single = \App\Actions\Iris\Catalogue\GetProductDetail::make()->jsonResponse(Product::find($lotionProduct->id), $request);

    expect($single['components'])->toBe([])
        ->and($single['specifications']['ingredients'])->toBe('Aqua, Glycerin');

    DB::table('products')->where('id', $lotionProduct->id)->update(['is_for_sale' => false, 'is_variant_leader' => false]);
    $lotionDocument = createAttachedMedia('Product', $lotionProduct->id, 'doc');

    $hiddenSingleDetail = \App\Actions\Iris\Catalogue\GetProductDetail::make()->jsonResponse(Product::find($starter->id), $request);
    $hiddenComponents   = collect($hiddenSingleDetail['components'])->keyBy('code');

    expect($hiddenComponents[$lotion->code]['url'])->toBeNull()
        ->and($hiddenComponents[$lotion->code]['ingredients'])->toBe('Aqua, Glycerin')
        ->and(collect($hiddenSingleDetail['attachments'])->pluck('media_ulid')->all())->toContain($lotionDocument->ulid);

    DB::table('model_has_attachments')->where('media_id', $lotionDocument->id)->delete();
    DB::table('products')->where('id', $lotionProduct->id)->update(['is_for_sale' => true]);
});

test('product detail carries the public documents of the selected variant', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $publicDocument = createAttachedMedia('Product', $product->id, 'doc');
    createAttachedMedia('Product', $product->id, 'doc_private');

    $attachments = \App\Actions\Iris\Catalogue\GetProductDetail::make()
        ->jsonResponse(Product::find($product->id), \Lorisleiva\Actions\ActionRequest::createFrom(request()))['attachments'];

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]['media_ulid'])->toBe($publicDocument->ulid)
        ->and($attachments[0]['scope'])->toBe('doc');
});

test('product detail carries the incoming stock only while the shop lets customers see it', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();
    $request = \Lorisleiva\Actions\ActionRequest::createFrom(request());

    $settings = $shop->settings;
    data_set($settings, 'catalog.allow_stocks_to_be_shown_on_iris', true);
    data_set($settings, 'catalog.allow_incoming_stocks_to_be_shown_on_iris', true);
    $shop->update(['settings' => $settings]);

    $detail = \App\Actions\Iris\Catalogue\GetProductDetail::make()->jsonResponse(Product::find($product->id), $request);

    expect($detail['allow_stocks_to_be_shown_on_iris'])->toBeTrue()
        ->and($detail['allow_incoming_stocks_to_be_shown_on_iris'])->toBeTrue()
        ->and($detail['incoming_stock'])->toBe(\App\Actions\Catalogue\Product\GetProductIncomingStock::run(Product::find($product->id), true));

    data_set($settings, 'catalog.allow_stocks_to_be_shown_on_iris', false);
    data_set($settings, 'catalog.allow_incoming_stocks_to_be_shown_on_iris', false);
    $shop->update(['settings' => $settings]);

    $detail = \App\Actions\Iris\Catalogue\GetProductDetail::make()->jsonResponse(Product::find($product->id), $request);

    expect($detail['allow_stocks_to_be_shown_on_iris'])->toBeFalse()
        ->and($detail['allow_incoming_stocks_to_be_shown_on_iris'])->toBeFalse()
        ->and($detail['incoming_stock'])->toBe([]);

    data_set($settings, 'catalog.allow_stocks_to_be_shown_on_iris', true);
    data_set($settings, 'catalog.allow_incoming_stocks_to_be_shown_on_iris', true);
    $shop->update(['settings' => $settings]);
});

test('bulk update product unit is scoped to shop', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    \App\Actions\Catalogue\Product\UpdateBulkProduct::make()->handle($shop, [
        'products' => [
            ['id' => $product->id, 'unit' => 'од.'],
        ],
    ]);
    expect($product->refresh()->unit)->toBe('од.');

    $otherShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    \App\Actions\Catalogue\Product\UpdateBulkProduct::make()->handle($otherShop, [
        'products' => [
            ['id' => $product->id, 'unit' => 'hacked'],
        ],
    ]);
    expect($product->refresh()->unit)->toBe('од.');
});

test('bulk update unit on a variant leader is applied to its variants', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $leader = $shop->products()->orderBy('id')->first();

    $minion = $leader->replicate();
    $minion->fill([
        'code' => $leader->code.'-MIN'.$leader->id,
        'slug' => $leader->slug.'-min'.$leader->id,
    ])->saveQuietly();

    $masterVariant = \App\Models\Masters\MasterVariant::create([
        'group_id' => $leader->group_id,
        'code'     => $leader->code,
        'data'     => ['products' => []],
    ]);
    $variant = \App\Models\Catalogue\Variant::create([
        'master_variant_id' => $masterVariant->id,
        'group_id'          => $leader->group_id,
        'organisation_id'   => $leader->organisation_id,
        'shop_id'           => $leader->shop_id,
        'family_id'         => $leader->family_id,
        'code'              => $leader->code,
        'data'              => ['products' => []],
    ]);
    $leader->updateQuietly(['variant_id' => $variant->id, 'is_variant_leader' => true]);
    $minion->updateQuietly(['variant_id' => $variant->id, 'is_variant_leader' => false]);

    \App\Actions\Catalogue\Product\UpdateBulkProduct::make()->handle($shop, [
        'products' => [
            ['id' => $leader->id, 'unit' => 'bag'],
        ],
    ]);

    expect($leader->refresh()->unit)->toBe('bag')
        ->and($minion->refresh()->unit)->toBe('bag');

    $leader->updateQuietly(['variant_id' => null, 'is_variant_leader' => false]);
    $minion->forceDelete();
});

test('audit archiver moves closed shop and discontinued product audits, history falls back to archive', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');

    Product::enableAuditing();
    $shop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $discontinuedProduct = $shop->products()->orderBy('id')->first();
    UpdateProduct::make()->action($discontinuedProduct, ['name' => 'audited before discontinuation']);

    $liveShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));

    expect(DB::table('audits')->where('auditable_type', 'Product')->where('auditable_id', $discontinuedProduct->id)->count())
        ->toBeGreaterThan(0);

    DB::table('products')->where('id', $discontinuedProduct->id)->update(['state' => ProductStateEnum::DISCONTINUED->value]);

    $dryRun = \App\Actions\Helpers\History\ArchiveAudits::make()->handle(dryRun: true);
    expect($dryRun)->toBeGreaterThan(0);

    $archived = \App\Actions\Helpers\History\ArchiveAudits::make()->handle();

    expect($archived)->toBeGreaterThan(0)
        ->and(DB::table('audits')->where('auditable_type', 'Product')->where('auditable_id', $discontinuedProduct->id)->exists())->toBeFalse()
        ->and(DB::connection('archive')->table('audits')->where('auditable_type', 'Product')->where('auditable_id', $discontinuedProduct->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('audits')->where('auditable_type', 'Shop')->where('auditable_id', $liveShop->id)->exists())->toBeTrue();

    request()->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'test-history', []))->name('test.history'));

    $footerNoteFor = function ($model): ?string {
        $table = new \App\InertiaTable\InertiaTable(request());
        (\App\Actions\Helpers\History\UI\IndexHistory::make()->tableStructure(model: $model))($table);
        $property = new \ReflectionProperty($table, 'footerNote');

        return $property->getValue($table);
    };

    $history = \App\Actions\Helpers\History\UI\IndexHistory::run($discontinuedProduct);
    expect($history->total())->toBeGreaterThan(0)
        ->and($footerNoteFor($discontinuedProduct))->toBe(__('Showing archived history.'));

    $archivedAudit = \App\Models\Helpers\Audit::on('archive')
        ->where('auditable_type', 'Product')->where('auditable_id', $discontinuedProduct->id)->first();
    expect($archivedAudit->getConnectionName())->toBe('archive')
        ->and($archivedAudit->user()->getQuery()->getModel()->getConnectionName())->not->toBe('archive');

    UpdateProduct::make()->action($discontinuedProduct->refresh(), ['name' => 'relaunched']);
    $mixedHistory = \App\Actions\Helpers\History\UI\IndexHistory::run($discontinuedProduct);
    expect($mixedHistory->total())->toBeGreaterThan(0)
        ->and($footerNoteFor($discontinuedProduct))->toContain(__('are archived.'));

    DB::table('shops')->where('id', $shop->id)->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::CLOSED->value]);
    $shopAuditsBefore = DB::table('audits')->where('shop_id', $shop->id)->count();
    expect($shopAuditsBefore)->toBeGreaterThan(0);

    \App\Actions\Helpers\History\ArchiveAudits::make()->handle(discontinued: false);

    expect(DB::table('audits')->where('shop_id', $shop->id)->exists())->toBeFalse()
        ->and(DB::connection('archive')->table('audits')->where('shop_id', $shop->id)->count())->toBeGreaterThanOrEqual($shopAuditsBefore)
        ->and(DB::table('audits')->where('auditable_type', 'Shop')->where('auditable_id', $liveShop->id)->exists())->toBeTrue();
});

test('audit archiver moves Aurora loop noise but keeps genuinely busy history', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');

    $insert = function (int $auditableId, callable $values) {
        $rows = [];
        for ($i = 0; $i < 1200; $i++) {
            [$old, $new] = $values($i);
            $rows[]      = [
                'auditable_type' => 'Product',
                'auditable_id'   => $auditableId,
                'event'          => 'updated',
                'tags'           => '[]',
                'old_values'     => json_encode(['duty_rate' => $old]),
                'new_values'     => json_encode(['duty_rate' => $new]),
                'url'            => 'artisan fetch:histories --organisations aw',
                'source_id'      => 'loop-test-'.$auditableId.'-'.$i,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        DB::table('audits')->insert($rows);
    };

    $loopingId = 987001;
    $busyId    = 987002;
    $insert($loopingId, fn (int $i) => $i % 2 ? ['0%', '650%'] : ['650%', '0%']);
    $insert($busyId, fn (int $i) => [$i.'%', ($i + 1).'%']);

    $archived = \App\Actions\Helpers\History\ArchiveAudits::make()
        ->handle(closedShops: false, discontinued: false, auroraLoops: true);

    expect($archived)->toBe(1200)
        ->and(DB::table('audits')->where('auditable_id', $loopingId)->exists())->toBeFalse()
        ->and(DB::connection('archive')->table('audits')->where('auditable_id', $loopingId)->count())->toBe(1200)
        ->and(DB::table('audits')->where('auditable_id', $busyId)->count())->toBe(1200);
});

test('audit archiver moves the whole trail of records older than the age cutoff', function () {
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => 'archive'])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists archive');

    $shop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    [, $product] = createProduct($shop);
    $order = createOrder(createCustomer($shop), $product);

    DB::table('audits')->insert([
        'auditable_type' => 'Order',
        'auditable_id'   => $order->id,
        'event'          => 'updated',
        'tags'           => '[]',
        'old_values'     => json_encode(['state' => 'a']),
        'new_values'     => json_encode(['state' => 'b']),
        'source_id'      => 'aged-test-backfill',
        'created_at'     => now()->subDay(),
        'updated_at'     => now()->subDay(),
    ]);

    $orderAudits = fn (): int => DB::table('audits')->where('auditable_type', 'Order')->where('auditable_id', $order->id)->count();

    DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subDays(5)]);
    $liveAudits = $orderAudits();

    expect($liveAudits)->toBeGreaterThan(1)
        ->and(\App\Actions\Helpers\History\ArchiveAudits::make()->handle(closedShops: false, discontinued: false, aged: true))->toBe(0)
        ->and($orderAudits())->toBe($liveAudits);

    DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subDays(150)]);

    $archived = \App\Actions\Helpers\History\ArchiveAudits::make()->handle(closedShops: false, discontinued: false, aged: true);

    expect($archived)->toBe($liveAudits)
        ->and($orderAudits())->toBe(0)
        ->and(DB::connection('archive')->table('audits')->where('auditable_type', 'Order')->where('auditable_id', $order->id)->count())->toBe($liveAudits);
});

test('noise audit purge deletes flag only audits and keeps real history', function () {
    $insert = function (string $auditableType, int $auditableId, array $newValues) {
        DB::table('audits')->insert([
            'auditable_type' => $auditableType,
            'auditable_id'   => $auditableId,
            'event'          => 'updated',
            'tags'           => '[]',
            'old_values'     => json_encode([]),
            'new_values'     => json_encode($newValues),
            'source_id'      => 'noise-test-'.$auditableType.'-'.$auditableId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    };

    $insert('Portfolio', 989001, ['platform_status' => false, 'exist_in_platform' => false]);
    $insert('Portfolio', 989002, ['status' => false]);
    $insert('Portfolio', 989003, ['platform_status' => false, 'status' => false]);
    $insert('EbayUser', 989004, ['settings.credentials.ebay_access_token' => '*********']);
    $insert('CustomerSalesChannel', 989005, ['number_portfolios' => 3, 'number_portfolio_broken' => 1]);
    $insert('CustomerSalesChannel', 989006, ['state' => 'card_saved']);

    expect(\App\Actions\Helpers\History\PurgeNoiseAudits::make()->handle(dryRun: true))->toBe(3);

    $deleted = \App\Actions\Helpers\History\PurgeNoiseAudits::make()->handle();

    expect($deleted)->toBe(3)
        ->and(DB::table('audits')->whereIn('auditable_id', [989001, 989004, 989005])->exists())->toBeFalse()
        ->and(DB::table('audits')->whereIn('auditable_id', [989002, 989003, 989006])->count())->toBe(3);
});

test('retina new arrivals hide exclusive products from other customers and families off the website', function () {
    list($organisation, $user, $shop) = createShop();

    $newCustomer = fn () => \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $shop,
        \App\Models\CRM\Customer::factory()->definition(),
    );
    $customerA = $newCustomer();
    $customerB = $newCustomer();

    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();
    DB::table('products')->where('id', $product->id)->update([
        'price'             => 10,
        'is_for_sale'       => true,
        'is_minion_variant' => false,
        'is_in_website'     => true,
        'state'             => ProductStateEnum::ACTIVE->value,
        'status'            => \App\Enums\Catalogue\Product\ProductStatusEnum::FOR_SALE->value,
    ]);
    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, ['customer_ids' => []]);
    DB::table('product_categories')->where('id', $product->family_id)->update(['is_in_website' => true]);

    $codesFor = fn (\App\Models\CRM\Customer $customer) => collect(
        \App\Actions\Retina\Ecom\NewArrival\UI\IndexRetinaEcomNewArrivals::make()->handle($customer)->items()
    )->pluck('code');

    expect($codesFor($customerA))->toContain($product->code)
        ->and($codesFor($customerB))->toContain($product->code);

    DB::table('product_categories')->where('id', $product->family_id)->update(['is_in_website' => false]);
    expect($codesFor($customerA))->not->toContain($product->code);
    DB::table('product_categories')->where('id', $product->family_id)->update(['is_in_website' => true]);

    \App\Actions\Catalogue\Product\SyncProductExclusiveCustomers::make()->action($product, [
        'customer_ids' => [$customerA->id],
    ]);
    DB::table('products')->where('id', $product->id)->update(['is_for_sale' => true, 'is_in_website' => true, 'status' => \App\Enums\Catalogue\Product\ProductStatusEnum::FOR_SALE->value]);

    DB::table('product_categories')->where('id', $product->family_id)->update(['is_in_website' => true]);
    expect($codesFor($customerA))->toContain($product->code)
        ->and($codesFor($customerB))->not->toContain($product->code);
});

test('iris collection lists the product that owns a member product webpage', function () {
    list($organisation, $user, $shop) = createShop();
    $website = createWebsite($shop);

    createProduct($shop);
    $bulk   = $shop->products()->orderBy('id')->first();
    $sample = StoreProduct::make()->action($bulk->family, array_merge(
        Product::factory()->definition(),
        ['trade_units' => [['id' => $bulk->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 2]
    ));

    $webpage = StoreProductWebpage::make()->action($sample);
    DB::table('products')->whereIn('id', [$bulk->id, $sample->id])->update([
        'is_for_sale' => true,
        'state'       => ProductStateEnum::ACTIVE->value,
        'webpage_id'  => $webpage->id,
    ]);

    $collection = StoreCollection::make()->action($shop, [
        'code'        => 'Oils',
        'name'        => 'Oils',
        'description' => 'Oils',
    ]);
    AttachModelToCollection::make()->action($collection, $bulk);

    $request = \Lorisleiva\Actions\ActionRequest::createFrom(request());
    $request->merge(['website' => $website]);
    $listed = fn () => collect(IndexIrisCatalogue::make()->initialisation($request)->handle([
        'scope'      => 'product',
        'parent'     => 'collection',
        'parent_key' => $collection->id,
    ])->items())->pluck('code');

    expect($listed()->all())->toBe([$sample->code]);

    AttachModelToCollection::make()->action($collection, $sample);

    expect($listed()->all())->toBe([$sample->code]);
});

test('shop products json carries the outer size from the stock, not the product units', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $product = UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $orgStock = $this->orgStock1;
    $orgStock->update(['packed_in' => 6]);

    $product->update(['units' => 1, 'is_for_sale' => true]);
    $product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);

    $products = \App\Actions\Catalogue\Product\Json\GetProductsInShop::make()->handle($shop);
    $row      = collect($products->items())->firstWhere('id', $product->id);

    expect($row)->not->toBeNull()
        ->and((int) $row->packed_in)->toBe(6)
        ->and((float) $row->units)->toBe(1.0);

    $secondStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        $orgStock->stock,
        array_merge(\App\Models\Inventory\OrgStock::factory()->definition(), ['code' => 'PACKED-IN-2ND']),
    );
    $product->orgStocks()->sync([$orgStock->id => ['quantity' => 1], $secondStock->id => ['quantity' => 1]]);

    $multi = collect(\App\Actions\Catalogue\Product\Json\GetProductsInShop::make()->handle($shop)->items())
        ->firstWhere('id', $product->id);

    expect($multi->packed_in)->toBeNull();
});

test('an on-demand stock never caps a product, and the shop products json reports what is on the shelf', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $product = UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $onDemandStock      = $this->orgStock1;
    $stockedStock       = $this->orgStock2;
    $stockAttributes    = ['is_on_demand', 'has_been_in_warehouse', 'quantity_available'];
    $originalOnDemand   = $onDemandStock->only($stockAttributes);
    $originalStocked    = $stockedStock->only($stockAttributes);
    $originalIsForSale  = $product->is_for_sale;
    $originalStockLinks = $product->orgStocks->mapWithKeys(fn ($orgStock) => [$orgStock->id => ['quantity' => $orgStock->pivot->quantity]])->all();

    $shelfRow = function () use ($shop, $product): ?array {
        $products = \App\Actions\Catalogue\Product\Json\GetProductsInShop::make()->handle($shop);
        $data     = \App\Http\Resources\Catalogue\ProductsWebpageResource::collection($products)->response()->getData(true)['data'];

        return collect($data)->firstWhere('id', $product->id);
    };

    try {
        $product->update(['is_for_sale' => true]);
        $onDemandStock->update(['is_on_demand' => true, 'has_been_in_warehouse' => true, 'quantity_available' => 0]);
        $stockedStock->update(['is_on_demand' => false, 'has_been_in_warehouse' => true, 'quantity_available' => 7]);

        $product->orgStocks()->sync([$onDemandStock->id => ['quantity' => 20]]);
        ProductHydrateAvailableQuantity::run($product->refresh());
        $product->refresh();

        expect($product->is_on_demand)->toBeTrue()
            ->and($product->available_quantity)->toBe(ProductHydrateAvailableQuantity::ON_DEMAND_QUANTITY)
            ->and($shelfRow())->toMatchArray(['is_on_demand' => true, 'shelf_quantity' => 0]);

        $onDemandStock->update(['quantity_available' => 100]);
        ProductHydrateAvailableQuantity::run($product->refresh());
        request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
        request()->query->set('filter', ['global' => $product->code]);
        $catalogueRow = collect(
            \App\Http\Resources\Catalogue\ProductsResource::collection(
                \App\Actions\Catalogue\Product\UI\IndexProductsInCatalogue::make()->handle($shop)
            )->response()->getData(true)['data']
        )->firstWhere('id', $product->id);

        expect($product->refresh()->available_quantity)->toBe(ProductHydrateAvailableQuantity::ON_DEMAND_QUANTITY)
            ->and($catalogueRow)->toMatchArray(['is_on_demand' => true, 'shelf_quantity' => 5]);

        $product->orgStocks()->sync([$onDemandStock->id => ['quantity' => 1], $stockedStock->id => ['quantity' => 1]]);
        $onDemandStock->update(['quantity_available' => 0]);
        ProductHydrateAvailableQuantity::run($product->refresh());
        $product->refresh();

        expect($product->is_on_demand)->toBeFalse()
            ->and($product->available_quantity)->toBe(7)
            ->and($shelfRow())->toMatchArray(['is_on_demand' => false, 'stock' => 7, 'shelf_quantity' => 0]);
    } finally {
        $onDemandStock->update($originalOnDemand);
        $stockedStock->update($originalStocked);
        $product->update(['is_for_sale' => $originalIsForSale]);
        $product->orgStocks()->sync($originalStockLinks);
        ProductHydrateAvailableQuantity::run($product->refresh());
    }
});

test('first availability is stamped once, when the product is for sale, in stock and on a live webpage', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $product = UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $orderable = ['is_for_sale' => true, 'has_live_webpage' => true, 'status' => ProductStatusEnum::FOR_SALE, 'first_available_at' => null];

    foreach ([['is_for_sale' => false], ['has_live_webpage' => false], ['status' => ProductStatusEnum::OUT_OF_STOCK], ['status' => ProductStatusEnum::COMING_SOON]] as $notOrderable) {
        $product->updateQuietly(array_merge($orderable, $notOrderable));
        expect(ProductHydrateFirstAvailableAt::run($product->refresh())->first_available_at)->toBeNull();
    }

    $product->updateQuietly($orderable);
    $firstAvailableAt = ProductHydrateFirstAvailableAt::run($product->refresh())->first_available_at;
    expect($firstAvailableAt)->not->toBeNull();

    $this->travel(5)->days();
    $product->updateQuietly(['status' => ProductStatusEnum::OUT_OF_STOCK]);
    ProductHydrateFirstAvailableAt::run($product->refresh());
    $product->updateQuietly(['status' => ProductStatusEnum::FOR_SALE]);
    expect(ProductHydrateFirstAvailableAt::run($product->refresh())->first_available_at->equalTo($firstAvailableAt))->toBeTrue();
    $this->travelBack();
});

test('repair backfills first availability on launched products only, and leaves existing dates alone', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $newProduct = fn (): Product => UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $launched       = $newProduct();
    $launchedStock  = $newProduct();
    $comingSoon     = $newProduct();
    $alreadyStamped = $newProduct();
    $stampedAt      = now()->subDays(3)->startOfSecond();
    $firstInStockAt = now()->subDays(90)->startOfSecond();

    $launched->updateQuietly(['status' => ProductStatusEnum::OUT_OF_STOCK, 'first_available_at' => null, 'first_in_stock_at' => null]);
    $launchedStock->updateQuietly(['status' => ProductStatusEnum::FOR_SALE, 'first_available_at' => null, 'first_in_stock_at' => $firstInStockAt]);
    $comingSoon->updateQuietly(['status' => ProductStatusEnum::COMING_SOON, 'first_available_at' => null]);
    $alreadyStamped->updateQuietly(['status' => ProductStatusEnum::FOR_SALE, 'first_available_at' => $stampedAt]);

    foreach ([$launched, $launchedStock, $comingSoon, $alreadyStamped] as $product) {
        RepairProductsFirstAvailableAt::run($product->refresh());
    }

    expect($launched->refresh()->first_available_at->equalTo($launched->created_at->startOfSecond()))->toBeTrue()
        ->and($launchedStock->refresh()->first_available_at->equalTo($firstInStockAt))->toBeTrue()
        ->and($comingSoon->refresh()->first_available_at)->toBeNull()
        ->and($alreadyStamped->refresh()->first_available_at->equalTo($stampedAt))->toBeTrue();
});

test('a product sold or live with stock before it is stamped available, or created long before the backfill day, is not new in', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $newProduct = fn (): Product => UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );
    $soldAt   = now()->subYear()->startOfSecond();
    $sellOnce = function (Product $product) use ($shop, $soldAt): void {
        $invoice = StoreInvoice::make()->action(createCustomer($shop), Invoice::factory()->definition());
        StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
            'date'            => $soldAt,
            'tax_category_id' => $invoice->tax_category_id,
            'quantity'        => 1,
            'gross_amount'    => 10,
            'net_amount'      => 10,
        ]);
    };

    $relaunched     = $newProduct();
    $wronglyStamped = $newProduct();
    $neverSold      = $newProduct();
    $oldNeverSold   = $newProduct();
    $liveWithStock  = $newProduct();
    $ranOutBefore   = $newProduct();
    $sellOnce($relaunched);
    $sellOnce($wronglyStamped);

    $relaunched->updateQuietly(['is_for_sale' => true, 'has_live_webpage' => true, 'status' => ProductStatusEnum::FOR_SALE, 'first_available_at' => null]);
    expect(ProductHydrateFirstAvailableAt::run($relaunched->refresh())->first_available_at->equalTo($soldAt))->toBeTrue();

    $stampedAt = now()->subDays(3)->startOfSecond();
    $wronglyStamped->updateQuietly(['first_available_at' => $stampedAt]);
    $neverSold->updateQuietly(['first_available_at' => $stampedAt, 'back_in_stock_since' => null]);
    $createdLongBefore = now()->subYears(2)->startOfSecond();
    $oldNeverSold->updateQuietly(['first_available_at' => $stampedAt, 'created_at' => $createdLongBefore, 'back_in_stock_since' => null]);

    $liveSince    = now()->subMonths(4)->startOfSecond();
    $inStockSince = now()->subMonths(3)->startOfSecond();
    foreach ([$liveWithStock, $ranOutBefore] as $product) {
        $webpage = $product->refresh()->webpage ?? StoreProductWebpage::make()->action($product);
        $webpage->updateQuietly(['live_at' => $liveSince]);
    }
    $liveWithStock->updateQuietly(['first_available_at' => $stampedAt, 'back_in_stock_since' => $inStockSince, 'out_of_stock_since' => null]);
    $ranOutBefore->updateQuietly(['first_available_at' => $stampedAt, 'back_in_stock_since' => now()->subMonths(6), 'out_of_stock_since' => now()->subMonths(5)]);

    $this->artisan('repair:products_first_available_at', ['--stamped-since' => now()->subDays(5)->toDateString(), '--backfill-day' => $stampedAt->toDateString()])->assertExitCode(0);

    expect($wronglyStamped->refresh()->first_available_at->equalTo($soldAt))->toBeTrue()
        ->and(ProductBadgeEnum::resolve($wronglyStamped->first_available_at, null))->toBeNull()
        ->and($neverSold->refresh()->first_available_at->equalTo($stampedAt))->toBeTrue()
        ->and(ProductBadgeEnum::resolve($neverSold->first_available_at, null))->toBe(ProductBadgeEnum::NEW_IN)
        ->and($oldNeverSold->refresh()->first_available_at->equalTo($createdLongBefore))->toBeTrue()
        ->and($liveWithStock->refresh()->first_available_at->equalTo($inStockSince))->toBeTrue()
        ->and($ranOutBefore->refresh()->first_available_at->equalTo($stampedAt))->toBeTrue();
});

test('the out of stock date is set when stock runs out and is kept while the product stays out', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    $product = UpdateProduct::make()->action(
        StoreProduct::make()->action($shop, array_merge(Product::factory()->definition(), ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 10])),
        ['state' => ProductStateEnum::ACTIVE]
    );

    $stock           = $this->orgStock2;
    $stockAttributes = ['is_on_demand', 'has_been_in_warehouse', 'quantity_available'];
    $originalStock   = $stock->only($stockAttributes);

    $hydrateWithStock = function (int $quantity) use ($product, $stock): Product {
        $stock->update(['is_on_demand' => false, 'has_been_in_warehouse' => true, 'quantity_available' => $quantity]);
        $product->updateQuietly(['is_for_sale' => true]);

        return ProductHydrateAvailableQuantity::run($product->refresh())->refresh();
    };

    try {
        $product->orgStocks()->sync([$stock->id => ['quantity' => 1]]);

        $hydrateWithStock(7);
        $product->updateQuietly(['out_of_stock_since' => now()->subDays(40)]);

        $wentOut = $hydrateWithStock(0);
        expect(Carbon\Carbon::parse($wentOut->out_of_stock_since)->diffInMinutes(now()))->toBeLessThan(5);

        $product->updateQuietly(['out_of_stock_since' => now()->subHours(30)]);
        $stillOut = $hydrateWithStock(0);
        expect(Carbon\Carbon::parse($stillOut->out_of_stock_since)->diffInHours(now()))->toBeGreaterThanOrEqual(29);

        $back = $hydrateWithStock(7);
        expect(Carbon\Carbon::parse($back->back_in_stock_since)->diffInMinutes(now()))->toBeLessThan(5)
            ->and(Carbon\Carbon::parse($back->out_of_stock_since)->diffInHours(now()))->toBeGreaterThanOrEqual(29);
    } finally {
        $stock->update($originalStock);
    }
});

test('faire case size change flags the product for units review until its trade units are saved', function () {
    $seederShop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    [, $seederProduct] = createProduct($seederShop);
    SyncProductTradeUnits::run($seederProduct, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);

    $faireShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::FAIRE->value,
    ]));
    $faireShop->updateQuietly(['seeder_shop_id' => $seederShop->id]);
    $product = StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), [
        'code'           => $seederProduct->code,
        'trade_units'    => [['id' => $this->tradeUnit1->id, 'quantity' => 1]],
        'price'          => 10,
        'marketplace_id' => 'variant_1',
    ]));
    expect((float) $product->units)->toBe(1.0)->and($product->units_review)->toBeNull();

    $faireVariant = ['id' => 'variant_1', 'sku' => $product->code, 'name' => 'default', 'lifecycle_state' => 'PUBLISHED', 'prices' => [['retail_price' => ['currency' => $faireShop->currency->code, 'amount_minor' => 3000], 'wholesale_price' => ['currency' => $faireShop->currency->code, 'amount_minor' => 1000]]]];
    $faireProduct = ['id' => 'product_1', 'name' => 'Gift box', 'description' => 'Red', 'lifecycle_state' => 'PUBLISHED', 'unit_multiplier' => 3, 'variants' => [$faireVariant]];
    \App\Actions\Catalogue\Shop\External\Faire\GetFaireProducts::make()->upsertFaireProduct($faireShop, $faireProduct);
    $product->refresh();
    expect((float) $product->units)->toBe(3.0)
        ->and($product->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);

    \App\Actions\Catalogue\Shop\External\Faire\GetFaireProducts::make()->upsertFaireProduct($faireShop, $faireProduct);
    expect($product->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);

    $product->updateQuietly(['units_review' => null]);
    expect(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::make()->handle())->toBe(1)
        ->and($product->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);

    \App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct::make()->action($product, ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 3]]]);
    $product->refresh();
    expect($product->units_review)->toBeNull()
        ->and((float) $product->tradeUnits->first()->pivot->quantity)->toBe(3.0)
        ->and(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::make()->handle())->toBe(0);
});

test('remodelling a seeder product sends its faire copies to case size review', function () {
    $seederShop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    [, $seederProduct] = createProduct($seederShop);
    SyncProductTradeUnits::run($seederProduct, [['id' => $this->tradeUnit1->id, 'quantity' => 12]]);
    $seederProduct->updateQuietly(['units' => 12]);

    $faireShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::FAIRE->value,
    ]));
    $faireShop->updateQuietly(['seeder_shop_id' => $seederShop->id]);
    $faireCopy = StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), [
        'code'        => $seederProduct->code,
        'trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 12]],
        'price'       => 10,
    ]));
    $faireCopy->updateQuietly(['units' => 12, 'state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]);
    $flagger = \App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::make();

    UpdateProduct::make()->action($seederProduct, ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 12]]]);
    expect($faireCopy->refresh()->units_review)->toBeNull();
    $alreadyAdrift = $flagger->flagCopiesOfRemodelledSeederProducts(dryRun: true);

    $seederProduct->updateQuietly(['units' => 1]);
    SyncProductTradeUnits::run($seederProduct, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    expect($flagger->flagCopiesOfRemodelledSeederProducts(dryRun: true))->toBe($alreadyAdrift + 1);
    $faireCopy->updateQuietly(['units_review' => null]);
    UpdateProduct::make()->action($seederProduct, ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]]]);
    expect($faireCopy->refresh()->units_review)->toBeNull();
    $flagger->flagCopiesOfRemodelledSeederProducts();
    expect($faireCopy->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET)
        ->and($faireCopy->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);

    $faireCopy->updateQuietly(['units_review' => null]);
    UpdateProduct::make()->action($seederProduct, ['trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 2]], 'has_independent_units' => true]);
    expect($faireCopy->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);

    $faireCopy->updateQuietly(['units_review' => null]);
    UpdateProduct::make()->action($seederProduct, ['units' => 3], strict: false);
    expect($faireCopy->refresh()->units_review)->toBe(\App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch::BUCKET);
});

test('a product faire no longer returns is discontinued and comes back when republished', function () {
    $faireShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::FAIRE->value,
    ]));
    $live = StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), ['price' => 10, 'marketplace_id' => 'variant_live']));
    $gone = StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), ['price' => 10, 'marketplace_id' => 'variant_gone']));
    $live->updateQuietly(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]);
    $gone->updateQuietly(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE]);
    foreach (range(1, 3) as $i) {
        StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), ['price' => 10, 'marketplace_id' => "variant_live_$i"]));
    }

    $prices       = [['retail_price' => ['currency' => $faireShop->currency->code, 'amount_minor' => 3000], 'wholesale_price' => ['currency' => $faireShop->currency->code, 'amount_minor' => 1000]]];
    $faireProduct = ['id' => 'product_1', 'name' => 'Gift box', 'description' => 'Red', 'lifecycle_state' => 'PUBLISHED', 'unit_multiplier' => 1, 'variants' => [
        ['id' => 'variant_live', 'sku' => $live->code, 'name' => 'default', 'lifecycle_state' => 'PUBLISHED', 'prices' => $prices],
        ['id' => 'variant_draft', 'sku' => 'DRAFT-1', 'name' => 'draft', 'lifecycle_state' => 'DRAFT', 'prices' => $prices],
    ]];

    $action         = \App\Actions\Catalogue\Shop\External\Faire\GetFaireProducts::make();
    $liveVariantIds = $action->getLiveFaireVariantIds([$faireProduct]);

    expect($liveVariantIds)->toBe(['variant_live' => true]);

    $liveVariantIds += ['variant_live_1' => true, 'variant_live_2' => true, 'variant_live_3' => true];

    expect($action->discontinueProductsGoneFromFaire($faireShop, $liveVariantIds))->toBe(1)
        ->and($live->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE)
        ->and($gone->refresh()->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED)
        ->and($action->discontinueProductsGoneFromFaire($faireShop, $liveVariantIds))->toBe(0);

    $faireProduct['variants'][] = ['id' => 'variant_gone', 'sku' => $gone->code, 'name' => 'back', 'lifecycle_state' => 'PUBLISHED', 'prices' => $prices];
    $action->upsertFaireProduct($faireShop, $faireProduct);
    $gone->refresh();

    expect($gone->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::IN_PROCESS)
        ->and($gone->status)->not->toBe(\App\Enums\Catalogue\Product\ProductStatusEnum::DISCONTINUED);

    SyncProductTradeUnits::run($gone, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $gone->updateQuietly(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED, 'status' => \App\Enums\Catalogue\Product\ProductStatusEnum::DISCONTINUED]);
    $action->upsertFaireProduct($faireShop, $faireProduct);
    $gone->refresh();

    expect($gone->state)->toBe(\App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE)
        ->and($gone->status)->not->toBe(\App\Enums\Catalogue\Product\ProductStatusEnum::DISCONTINUED);
});

test('faire discontinue is skipped when faire returns far fewer live products than aiku has', function () {
    $faireShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::FAIRE->value,
    ]));
    $products = collect(range(1, 4))->map(fn ($i) => StoreProduct::make()->action($faireShop, array_merge(Product::factory()->definition(), ['price' => 10, 'marketplace_id' => "short_fetch_$i"])));

    $discontinued = \App\Actions\Catalogue\Shop\External\Faire\GetFaireProducts::make()->discontinueProductsGoneFromFaire($faireShop, ['short_fetch_1' => true, 'short_fetch_2' => true]);

    expect($discontinued)->toBe(0)
        ->and($products->every(fn ($product) => $product->refresh()->state !== \App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED))->toBeTrue();
});

test('a packing slip faire refuses is retried, and the last refusal is reported without failing the job', function () {
    $faireShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), [
        'type'   => ShopTypeEnum::EXTERNAL->value,
        'engine' => \App\Enums\Catalogue\Shop\ShopEngineEnum::FAIRE->value,
    ]));
    [, $product] = createProduct($faireShop);
    $order = createOrder(createCustomer($faireShop), $product);
    $order->update(['external_id' => 'bo_refused_slip']);

    \Illuminate\Support\Facades\Http::fake(['*packing-slip-pdf*' => \Illuminate\Support\Facades\Http::response(['message' => 'Not found'], 404)]);

    expect(fn () => \App\Actions\Catalogue\Shop\External\Faire\DownloadFairePackingPdfSlip::make()->handle($order->id, isLastAttempt: false))
        ->toThrow(\Exception::class, 'Faire has no packing slip PDF yet');

    \App\Actions\Catalogue\Shop\External\Faire\DownloadFairePackingPdfSlip::run($order->id);

    expect(DB::table('model_has_attachments')->where('model_type', 'Order')->where('model_id', $order->id)->exists())->toBeFalse();
});

function createRetiredProductSharingReplacementWebpage(Shop $shop, array $tradeUnits): array
{
    $storeProduct = fn (string $code) => StoreProduct::make()->action($shop, array_merge(
        Product::factory()->definition(),
        ['code' => $code, 'trade_units' => $tradeUnits, 'price' => 45, 'unit' => 'bottle']
    ));

    $retired     = $storeProduct(fake()->unique()->lexify('ret????'));
    $replacement = $storeProduct($retired->code.'-10ml');

    $replacementOwnPage = StoreProductWebpage::make()->action($replacement);
    $sharedPage         = StoreProductWebpage::make()->action($retired);
    $replacementOwnPage->modelHasWebBlocks()->delete();

    $sharedPage->update(['state' => WebpageStateEnum::LIVE]);
    CloseWebpage::make()->action($replacementOwnPage, ['redirect_type' => \App\Enums\Web\Redirect\RedirectTypeEnum::PERMANENT->value, 'to_webpage_id' => $sharedPage->id]);
    $sharedPage->update(['model_id' => $replacement->id]);
    $replacement->update(['webpage_id' => $sharedPage->id, 'is_for_sale' => true]);
    $retired->update([
        'is_for_sale' => true,
        'data'        => array_merge($retired->data, ['retire_at_cutover' => true, 'replaced_by_product_id' => $replacement->id]),
    ]);

    return [$retired->refresh(), $replacement->refresh(), $sharedPage->refresh(), $replacementOwnPage->refresh()];
}

test('retire product into its replacement takes it off sale and discontinues it', function (Shop $shop) {
    [$retired, $replacement, $sharedPage] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);

    $retired = RetireProductIntoReplacement::make()->action($retired);

    expect($retired->is_main)->toBeFalse()
        ->and($retired->is_for_sale)->toBeFalse()
        ->and($retired->state)->toBe(ProductStateEnum::DISCONTINUED)
        ->and($sharedPage->refresh()->model_id)->toBe($replacement->id)
        ->and($replacement->refresh()->is_for_sale)->toBeTrue();
})->depends('create shop');

test('keep retired product as separate gives both products their own webpage', function (Shop $shop) {
    [$retired, $replacement, $sharedPage, $replacementOwnPage] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);

    $retired = KeepRetiredProductAsSeparate::make()->action($retired);
    $replacement->refresh();

    expect($retired->webpage_id)->toBe($sharedPage->id)
        ->and($sharedPage->refresh()->model_id)->toBe($retired->id)
        ->and($replacement->webpage_id)->toBe($replacementOwnPage->id)
        ->and($replacementOwnPage->refresh()->state)->toBe(WebpageStateEnum::LIVE)
        ->and($replacementOwnPage->redirect_webpage_id)->toBeNull()
        ->and($replacementOwnPage->webBlocks()->exists())->toBeTrue()
        ->and($replacementOwnPage->published_layout['web_blocks'] ?? [])->not->toBeEmpty()
        ->and(ShowIrisWebpage::make()->getWebpageID($shop->website, $sharedPage->url))->toBe($sharedPage->id)
        ->and(ShowIrisWebpage::make()->getWebpageID($shop->website, $replacementOwnPage->url))->toBe($replacementOwnPage->id)
        ->and($retired->webpage->id)->toBe($sharedPage->id)
        ->and($replacement->webpage->id)->toBe($replacementOwnPage->id)
        ->and($retired->is_for_sale)->toBeTrue()
        ->and($retired->data)->not->toHaveKey('retire_at_cutover')
        ->and($retired->data)->not->toHaveKey('replaced_by_product_id');
})->depends('create shop');

test('keep retired product as separate finds the shared webpage by url when the product lost its webpage id', function (Shop $shop) {
    [$retired, $replacement, $sharedPage] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $retired->update(['webpage_id' => null]);

    $retired = KeepRetiredProductAsSeparate::make()->action($retired->refresh());

    expect($retired->webpage_id)->toBe($sharedPage->id)
        ->and($sharedPage->refresh()->model_id)->toBe($retired->id)
        ->and($replacement->refresh()->webpage_id)->not->toBe($sharedPage->id);
})->depends('create shop');

test('retired product edit form hides the for sale toggle', function (Shop $shop) {
    [$retired] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $retired = RetireProductIntoReplacement::make()->action($retired);

    $fields = collect(EditProduct::make()->getBlueprint($retired->refresh()))->pluck('fields')->collapse();

    expect($retired->state)->toBe(ProductStateEnum::DISCONTINUED)
        ->and($fields->has('is_for_sale'))->toBeFalse();
})->depends('create shop');

test('retired product off sale offers its webpage back and hides create webpage while the replacement holds its url', function (Shop $shop) {
    [$retired] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $retired = RetireProductIntoReplacement::make()->action($retired);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [$shop->organisation->slug, $shop->slug, $retired->slug]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('retirement_decision.retire_route', null)
                ->where('retirement_decision.keep_route.name', 'grp.models.product.keep_as_separate')
                ->where('pageHead.actions', fn ($actions) => collect($actions)->doesntContain('label', 'Create Webpage'))
                ->etc()
        );
})->depends('create shop');

test('keep retired product as separate reactivates a discontinued product', function (Shop $shop) {
    [$retired, , $sharedPage] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $retired = RetireProductIntoReplacement::make()->action($retired);

    $retired = KeepRetiredProductAsSeparate::make()->action($retired);

    expect($retired->state)->toBe(ProductStateEnum::ACTIVE)
        ->and($retired->status)->not->toBe(ProductStatusEnum::DISCONTINUED)
        ->and($retired->webpage_id)->toBe($sharedPage->id)
        ->and($sharedPage->refresh()->state)->toBe(WebpageStateEnum::LIVE);
})->depends('create shop');

test('keep retired product as separate refuses a product exclusive to a customer', function (Shop $shop) {
    [$retired, $replacement, $sharedPage] = createRetiredProductSharingReplacementWebpage($shop, [['id' => $this->tradeUnit1->id, 'quantity' => 1]]);
    $owner = \App\Actions\CRM\Customer\StoreCustomer::make()->action($shop, \App\Models\CRM\Customer::factory()->definition());
    $retired->exclusiveCustomers()->attach($owner->id);
    $retired->update(['is_for_sale' => false]);

    expect(fn () => KeepRetiredProductAsSeparate::make()->action($retired))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and($retired->refresh()->is_for_sale)->toBeFalse()
        ->and($sharedPage->refresh()->model_id)->toBe($replacement->id);
})->depends('create shop');

test('product webpage replaces characters not allowed in webpage urls', function (Shop $shop) {
    $product = StoreProduct::make()->action($shop, array_merge(
        Product::factory()->definition(),
        ['code' => fake()->unique()->lexify('dot????').'-0.5L', 'trade_units' => [['id' => $this->tradeUnit1->id, 'quantity' => 1]], 'price' => 45, 'unit' => 'bottle']
    ));

    $webpage = StoreProductWebpage::make()->action($product);

    expect($webpage->url)->toBe(strtolower(str_replace('.', '-', $product->code)))
        ->and($product->refresh()->webpage_id)->toBe($webpage->id);
})->depends('create shop');

test('product barcode is left alone when it stops being a single trade unit', function () {
    $shop = Shop::orderBy('id')->first() ?? StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B->value]));
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();

    $this->tradeUnit1->update(['barcode' => '5060000000011']);
    $this->tradeUnit2->update(['barcode' => '5060000000028']);

    SyncProductTradeUnits::run($product, [
        ['id' => $this->tradeUnit1->id, 'quantity' => 1],
    ]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit::run(Product::find($product->id));
    $product->refresh();

    expect($product->barcode)->toBe('5060000000011');

    SyncProductTradeUnits::run($product, [
        ['id' => $this->tradeUnit1->id, 'quantity' => 1],
        ['id' => $this->tradeUnit2->id, 'quantity' => 1],
    ]);
    \App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit::run(Product::find($product->id));
    $product->refresh();

    /* Clearing it here is what emptied 561 products and pushed blank GTINs to live listings. */
    expect($product->barcode)->toBe('5060000000011');
});

test('customer service gets one stock notification for all their shops, new apart from back', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $shop = Shop::orderBy('id')->first();
    createProduct($shop);
    $product = $shop->products()->orderByDesc('id')->first();
    $shop->updateQuietly(['is_aiku' => true, 'state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);
    \Illuminate\Support\Facades\Cache::forget(\App\Actions\Catalogue\Shop\NotifyShopStockArrivals::SENT_MARKER);

    $agent = $this->adminGuest->getUser();
    $agent->assignRole(Role::where('name', \App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $shop))->firstOrFail());

    $product->updateQuietly(['is_for_sale' => true, 'state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'available_quantity' => 4, 'back_in_stock_since' => now()->subMinute(), 'first_in_stock_at' => now()->subMinute()]);

    expect(\App\Actions\Catalogue\Shop\NotifyShopStockArrivals::run())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Catalogue\Shop\NotifyShopStockArrivals::run())->toBe(0);

    \Illuminate\Support\Facades\Notification::assertSentToTimes($agent, \App\Notifications\ShopStockArrivalsNotification::class, 1);
    \Illuminate\Support\Facades\Notification::assertSentTo($agent, \App\Notifications\ShopStockArrivalsNotification::class, function ($notification) use ($product, $shop) {
        $data = $notification->toArray(null);

        return in_array($product->code, $notification->newCodes, true)
            && !in_array($product->code, $notification->backCodes, true)
            && in_array($shop->code, $notification->shopCodes, true)
            && str_contains($data['body'], 'New in stock: ')
            && str_contains($data['title'], $shop->code);
    });
});

test('a shop short name is saved from the edit form and seeded only where it is missing', function () {
    [, , $shop] = createOwnShop('shop short name');

    $shop->update(['code' => 'CZ', 'short_name' => null]);
    expect(\App\Actions\Catalogue\Shop\Seeders\SeedShopShortNames::make()->handle(true))->toBe(["CZ: $shop->name -> Česko"])
        ->and($shop->fresh()->short_name)->toBeNull();

    \App\Actions\Catalogue\Shop\Seeders\SeedShopShortNames::make()->handle();
    expect($shop->fresh()->short_name)->toBe('Česko');

    UpdateShop::make()->action($shop->fresh(), ['short_name' => 'Czechia']);
    \App\Actions\Catalogue\Shop\Seeders\SeedShopShortNames::make()->handle();

    expect($shop->fresh()->short_name)->toBe('Czechia')
        ->and(\App\Http\Resources\Catalogue\ShopResource::make($shop->fresh())->resolve()['short_name'])->toBe('Czechia');
});

test('catalogue viewers cannot create sub departments or edit variants, and shipping country routes are gone', function () {
    [$organisation, , $shop] = createOwnShop('catalogue edit permissions');
    [, $product]             = createProduct($shop);
    $department              = $shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->firstOrFail();
    $family                  = $product->family;

    $masterVariant = \App\Models\Masters\MasterVariant::create([
        'group_id' => $product->group_id,
        'code'     => $product->code.'-perm',
        'data'     => ['products' => []],
    ]);
    $variant = \App\Models\Catalogue\Variant::create([
        'master_variant_id' => $masterVariant->id,
        'group_id'          => $product->group_id,
        'organisation_id'   => $product->organisation_id,
        'shop_id'           => $shop->id,
        'family_id'         => $family->id,
        'code'              => $product->code.'-perm',
        'data'              => ['products' => []],
    ]);

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);

    patch(route('grp.models.variant.update', $variant->id), ['option_translations' => ['Plug' => 'Wtyczka']])->assertForbidden();
    \Pest\Laravel\post(route('grp.models.department.sub_department.store', $department->id), ['code' => 'permsub', 'name' => 'Perm sub'])->assertForbidden();
    get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.create', [$organisation->slug, $shop->slug, $department->slug]))->assertForbidden();
    get(route('grp.org.shops.show.catalogue.families.show.variants.edit', [$organisation->slug, $shop->slug, $family->slug, $variant->slug]))->assertForbidden();

    expect($variant->refresh()->option_translations)->toBeEmpty()
        ->and(\App\Models\Catalogue\ProductCategory::where('shop_id', $shop->id)->where('code', 'permsub')->exists())->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('grp.models.shipping_country.store'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('grp.models.shipping_country.update'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('grp.models.shipping_country.delete'))->toBeFalse();

    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.edit", "products.$shop->id.view"]);

    get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.create', [$organisation->slug, $shop->slug, $department->slug]))->assertOk();
});

test('collection sub collections search filters on the collection name and code', function () {
    list($organisation, $user, $shop) = createShop();

    $parent = StoreCollection::make()->action($shop, ['code' => 'PARENT-SRCH', 'name' => 'Parent']);
    $child  = StoreCollection::make()->action($shop, ['code' => 'CHILD-SRCH', 'name' => 'Wanted child']);
    AttachModelToCollection::make()->action($parent, $child);

    $route = \Mockery::mock(\Illuminate\Routing\Route::class);
    $route->shouldReceive('getName')->andReturn('grp.org.shops.show.catalogue.collections.show');
    $request = request()->merge(['filter' => ['global' => 'wanted']]);
    $request->setRouteResolver(fn () => $route);

    $found = \App\Actions\Catalogue\Collection\UI\IndexCollectionsInCollection::run($parent);

    expect($found->pluck('code')->all())->toBe(['CHILD-SRCH']);
});

test('a product image is deleted by staff with web edit on its shop', function () {
    list($organisation, $user, $shop) = createShop();
    createProduct($shop);
    $product = $shop->products()->orderBy('id')->first();
    $product->updateQuietly(['is_single_trade_unit' => false]);

    $path = tempnam(sys_get_temp_dir(), 'pimg').'.png';
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $media = \App\Actions\Helpers\Media\StoreMediaFromFile::run($product, ['path' => $path, 'originalName' => 'pimg.png', 'extension' => 'png', 'checksum' => md5_file($path)], 'image');
    $product->images()->attach($media->id, ['group_id' => $product->group_id, 'organisation_id' => $product->organisation_id, 'scope' => 'image', 'data' => '{}']);

    $staff = \App\Models\SysAdmin\User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($staff, ["web.$shop->id.edit", "web.$shop->id.view"]);

    \Pest\Laravel\delete(route('grp.models.product.images.delete_images', [$product->id, $media->id]))->assertSuccessful();

    expect($product->images()->where('media.id', $media->id)->exists())->toBeFalse();
});

test('catalogue json lookups answer only staff with the matching rule on the shop', function () {
    list($organisation, , $shop) = createShop();
    [, $product] = createProduct($shop);
    $family     = $product->family;
    $department = $product->department;

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);

    $rulesByUrls = [
        [
            [
                route('grp.json.product_category.families.index', $department->slug),
                route('grp.json.shop.product_categories_by_codes', [$shop->id, 'codes' => $department->code.','.$family->code]),
                route('grp.json.trade-units.all.under-product', $product->id),
                route('grp.json.product.external.trade-units-linked', $product->slug),
                route('grp.json.product_category.products_by_codes', [$family->id, 'codes' => $product->code]),
                route('grp.json.shop.products_by_codes', [$shop->id, 'codes' => $product->code]),
            ],
            "crm.$shop->id.view",
            "products.$shop->id.view",
        ],
        [
            [route('grp.json.shop.department_and_sub_departments', $shop->slug)],
            "products.$shop->id.view",
            "products.$shop->id.edit",
        ],
        [
            [
                route('grp.json.shop.families.for_related_web_block', $shop->slug),
                route('grp.json.shop.departments.for_related_web_block', $shop->slug),
                route('grp.json.shop.sub_departments.for_related_web_block', $shop->slug),
                route('grp.json.product_category.products.index', $family->id),
            ],
            "products.$shop->id.edit",
            "web.$shop->id.edit",
        ],
        [
            [route('grp.json.product_category.discontinuing_products.index', $family->id)],
            "products.$shop->id.edit",
            "discounts.$shop->id.edit",
        ],
    ];

    foreach ($rulesByUrls as [$urls, $refusedRule, $allowedRule]) {
        actingAsUserWithOnlyPermissions($user, [$refusedRule]);
        foreach ($urls as $url) {
            expect(get($url)->status())->toBe(403, $url);
        }

        actingAsUserWithOnlyPermissions($user, [$allowedRule]);
        foreach ($urls as $url) {
            expect(get($url)->status())->toBe(200, $url);
        }
    }
});

test('unused json lookups are removed, INI-076', function () {
    foreach ([
        'org_payment_service_providers.index', 'collection.parent.departments.index', 'collection.families.index', 'shop.product_categories',
        'collection.parent.sub_departments.index', 'variant.products', 'product_category.out_of_stock_products.index', 'collection.products.index',
        'product_category.see_also_products.index', 'product_category.top_products.index', 'mailshot.convert-to-page',
        'dashboard_custom-dates.masters_shops_sales', 'dashboard_custom-dates.group.organisation_sales', 'dashboard_custom-dates.group.shops_sales',
        'dashboard_custom-dates.group.invoice_categories_sales', 'dashboard_custom-dates.organisation.shops_sales', 'dashboard_custom-dates.organisation.invoice_categories_sales',
        'computers.index', 'printers.index', 'webpage.web_block_histories.index', 'web-block.web_block_histories.index', 'web-block-type.web_block_histories.index',
    ] as $name) {
        expect(\Illuminate\Support\Facades\Route::has("grp.json.$name"))->toBeFalse();
    }
});

test('invoices of a product answer catalogue staff of its shop, and a product of another shop is not found, INI-076', function () {
    list($organisation, , $shop) = createShop();
    list(, $product) = createProduct($shop);
    $otherShop = StoreShop::make()->action($organisation, Shop::factory()->definition());

    $url = route('grp.org.shops.show.catalogue.products.current_products.invoices', [$organisation->slug, $shop->slug, $product->slug]);

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $organisation->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["human-resources.$organisation->id.view"]);
    get($url)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);
    get($url)->assertOk();
    get(route('grp.org.shops.show.catalogue.products.current_products.invoices', [$organisation->slug, $otherShop->slug, $product->slug]))->assertNotFound();
});

test('a digital agency organisation runs a CRM only shop that no selling organisation can create, HELP-4297', function () {
    $digitalAgency = \App\Models\SysAdmin\Organisation::where('code', 'dagency')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action(
            $this->group,
            array_merge(\App\Models\SysAdmin\Organisation::factory()->definition(), [
                'code'    => 'dagency',
                'type'    => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::DIGITAL_AGENCY,
                'address' => \App\Models\Helpers\Address::factory()->definition(),
            ])
        );

    $digitalAgency->catalogueStats()->delete();
    $this->artisan('maintenance:add_missing_stats_to_digital_agency_organisation')->assertSuccessful();
    $this->artisan('maintenance:add_missing_stats_to_digital_agency_organisation')->assertSuccessful();
    $digitalAgency->refresh();

    expect($digitalAgency->catalogueStats)->not->toBeNull()
        ->and($digitalAgency->orderingStats()->count())->toBe(1)
        ->and($digitalAgency->getAccountsServiceProvider())->not->toBeNull();

    $shop = StoreShop::make()->action($digitalAgency, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::DIGITAL_AGENCY->value]));
    $digitalAgency->refresh();

    expect($shop->type)->toBe(ShopTypeEnum::DIGITAL_AGENCY)
        ->and($digitalAgency->catalogueStats->number_shops_type_digital_agency)->toBe(1)
        ->and($digitalAgency->group->catalogueStats->number_shops_type_digital_agency)->toBeGreaterThanOrEqual(1)
        ->and(\App\Enums\Dashboards\ShopDashboardSectionsEnum::forShop($shop))->toBe([\App\Enums\Dashboards\ShopDashboardSectionsEnum::CUSTOMERS])
        ->and(\App\Enums\Dashboards\ShopDashboardSectionsEnum::current($shop, [], 'target'))->toBe('customers')
        ->and($shop->outboxes()->pluck('code')->map(fn ($code) => $code->value)->all())->toContain('appointment_requested', 'new_appointment')
        ->and($shop->outboxes()->where('code', 'new_order')->exists())->toBeFalse();

    expect(fn () => StoreWebsite::make()->action($shop, Website::factory()->definition()))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::DIGITAL_AGENCY->value])))->toThrow(\Illuminate\Validation\ValidationException::class);

    $b2bShop = Shop::where('organisation_id', $this->organisation->id)->where('type', ShopTypeEnum::B2B)->first() ?? createShop()[2];
    expect(fn () => UpdateShop::make()->action($b2bShop, ['type' => ShopTypeEnum::DIGITAL_AGENCY->value]))->toThrow(\Illuminate\Validation\ValidationException::class);

    \App\Actions\SysAdmin\Organisation\HydrateOrganisations::run($digitalAgency);
    \App\Actions\SysAdmin\Organisation\HydrateOrganisations::run($this->organisation);
});
