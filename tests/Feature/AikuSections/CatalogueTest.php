<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 22:04:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Charge\HydrateCharge;
use App\Actions\Billables\Charge\Search\ReindexChargeSearch;
use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Charge\UpdateCharge;
use App\Actions\Billables\Rental\Search\ReindexRentalSearch;
use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Service\Search\ReindexServiceSearch;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Billables\Service\UpdateService;
use App\Actions\Catalogue\Collection\AttachModelsToCollection;
use App\Actions\Catalogue\Collection\DetachModelFromCollection;
use App\Actions\Catalogue\Collection\Search\ReindexCollectionSearch;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\CollectionCategory\StoreCollectionCategory;
use App\Actions\Catalogue\CollectionCategory\UpdateCollectionCategory;
use App\Actions\Catalogue\Product\DeleteProduct;
use App\Actions\Catalogue\Product\HydrateProducts;
use App\Actions\Catalogue\Product\Search\ReindexProductSearch;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\StoreProductVariant;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\HydrateDepartments;
use App\Actions\Catalogue\ProductCategory\HydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Search\ReindexProductCategorySearch;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Catalogue\Shop\HydrateShops;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\CollectionCategory;
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
        ->and($organisation->group->catalogueStats->number_shops)->toBe(1)
        ->and($organisation->group->catalogueStats->number_shops_type_b2b)->toBe(1)
        ->and($organisation->catalogueStats->number_shops)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_type_b2b)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_state_in_process)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_state_open)->toBe(0)
        ->and($shopRoles->count())->toBe(11)
        ->and($shopPermissions->count())->toBe(28);


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
    ->and(intval($webpage->model_id))->toBe($department->id);

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


test('create product', function (ProductCategory $family) {
    $orgStocks = [
        $this->orgStock1->id => [
            'quantity' => 1,
        ]
    ];

    $productData = array_merge(
        Product::factory()->definition(),
        [
            'org_stocks' => $orgStocks,
            'price'      => 100,
            'unit'       => 'unit'
        ]
    );

    $product = StoreProduct::make()->action($family, $productData);
    $product->refresh();


    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->state)->toBe(ProductStateEnum::IN_PROCESS)
        ->and($product->asset)->toBeInstanceOf(Asset::class)
        ->and($product->historicAsset)->toBeInstanceOf(HistoricAsset::class)
        ->and($product->tradeUnits()->count())->toBe(1)
        ->and($product->organisation->catalogueStats->number_products)->toBe(1)
        ->and($product->organisation->catalogueStats->number_current_products)->toBe(0)
        ->and($product->organisation->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($product->organisation->catalogueStats->number_assets_type_service)->toBe(0)
        ->and($product->group->catalogueStats->number_products)->toBe(1)
        ->and($product->group->catalogueStats->number_current_products)->toBe(0)
        ->and($product->group->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($family->department->stats->number_products)->toBe(1)
        ->and($family->department->stats->number_products_state_in_process)->toBe(1)
        ->and($family->department->stats->number_current_products)->toBe(0)
        ->and($family->stats->number_products)->toBe(1)
        ->and($family->stats->number_current_products)->toBe(0)
        ->and($product->department)->toBeInstanceOf(ProductCategory::class)
        ->and($product->department->stats->number_products)->toBe(1)
        ->and($product->department->stats->number_current_products)->toBe(0)
        ->and($product->shop->stats->number_assets_type_product)->toBe(1)
        ->and($product->stats->number_product_variants)->toBe(1);


    return $product;
})->depends('create family');

test('update product state to active', function (Product $product) {
    expect($product->state)->toBe(ProductStateEnum::IN_PROCESS);
    $product = UpdateProduct::make()->action(
        $product,
        [
            'state' => ProductStateEnum::ACTIVE
        ]
    );
    $product->refresh();

    expect($product->state)->toBe(ProductStateEnum::ACTIVE)
        ->and($product->group->catalogueStats->number_current_products)->toBe(1)
        ->and($product->organisation->catalogueStats->number_current_products)->toBe(1)
        ->and($product->shop->stats->number_current_products)->toBe(1)
        ->and($product->department->stats->number_current_products)->toBe(1)
        ->and($product->family->stats->number_current_products)->toBe(1)
        ->and($product->family->stats->number_products_state_active)->toBe(1)
        ->and($product->family->state)->toBe(ProductCategoryStateEnum::ACTIVE);

    return $product;
})->depends('create product');


test('create product with many org stocks', function ($shop) {
    $orgStocks = [
        $this->orgStock1->id  => [
            'quantity' => 1,
        ],
        $this->orgStock2->id => [
            'quantity' => 1,
        ]
    ];


    $productData = array_merge(
        Product::factory()->definition(),
        [
            'org_stocks'  => $orgStocks,
            'price'       => 99,
            'unit'        => 'pack'
        ]
    );


    $product = StoreProduct::make()->action($shop, $productData);
    $shop->refresh();

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->unit_relationship_type)->toBe(ProductUnitRelationshipType::MULTIPLE)
        ->and($product->tradeUnits()->count())->toBe(2)
        ->and($shop->stats->number_products)->toBe(2)
        ->and($product->asset->stats->number_historic_assets)->toBe(1)
        ->and($shop->group->catalogueStats->number_products)->toBe(2)
        ->and($shop->group->catalogueStats->number_assets_type_product)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_products)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_assets_type_product)->toBe(2);

    return $product;
})->depends('create family');

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
        ->and($product->asset->stats->number_historic_assets)->toBe(2);


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
        ->and($shop->group->catalogueStats->number_assets)->toBe(2)
        ->and($shop->group->catalogueStats->number_products)->toBe(2)
        ->and($shop->organisation->catalogueStats->number_products)->toBe(2);

    DeleteProduct::run($product);
    $shop->refresh();

    expect($shop->stats->number_products)->toBe(1)
        ->and($shop->group->catalogueStats->number_assets)->toBe(1)
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
        ->and($asset)->toBeInstanceOf(Asset::class)
        ->and($service->asset->stats->number_historic_assets)->toBe(1)
        ->and($group->catalogueStats->number_assets)->toBe(2)
        ->and($group->catalogueStats->number_products)->toBe(1)
        ->and($group->catalogueStats->number_services)->toBe(1)
        ->and($group->catalogueStats->number_assets_type_product)->toBe(1)
        ->and($group->catalogueStats->number_assets_type_service)->toBe(1)
        ->and($organisation->catalogueStats->number_products)->toBe(1)
        ->and($organisation->catalogueStats->number_assets_type_service)->toBe(1)
        ->and($shop->stats->number_assets)->toBe(2)
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

    return $service;
})->depends('create service');

test('create collection category', function ($shop) {
    $collectionCategory = StoreCollectionCategory::make()->action(
        $shop,
        [
            'code'        => 'AAA',
            'name'        => 'Cat one aaa',
            'description' => 'Cat one aaa description'
        ]
    );
    $shop->refresh();
    expect($collectionCategory)->toBeInstanceOf(CollectionCategory::class)
        ->and($shop->stats->number_collection_categories)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_collection_categories)->toBe(1)
        ->and($shop->group->catalogueStats->number_collection_categories)->toBe(1);


    return $collectionCategory;
})->depends('create shop');

test('create collection', function ($shop) {
    $collectionCategory = StoreCollection::make()->action(
        $shop,
        [
            'code'        => 'MyFColl',
            'name'        => 'My first collection',
            'description' => 'My first collection description'
        ]
    );
    $shop->refresh();
    expect($collectionCategory)->toBeInstanceOf(Collection::class)
        ->and($shop->stats->number_collections)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_collections)->toBe(1)
        ->and($shop->group->catalogueStats->number_collections)->toBe(1);


    return $collectionCategory;
})->depends('create shop');

test('create collection in a collection category', function (CollectionCategory $collectionCategory) {
    $collection = StoreCollection::make()->action(
        $collectionCategory,
        [
            'code'        => 'BBB',
            'name'        => 'Cat two bbb',
            'description' => 'Cat two bbb description'
        ]
    );

    $collectionCategory->refresh();
    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collectionCategory->stats->number_collections)->toBe(1)
        ->and($collectionCategory->shop->stats->number_collections)->toBe(2)
        ->and($collectionCategory->organisation->catalogueStats->number_collections)->toBe(2)
        ->and($collectionCategory->group->catalogueStats->number_collections)->toBe(2);


    return $collection;
})->depends('create collection category');

test('update collection', function ($collection) {
    expect($collection->name)->not->toBe('Updated Collection Name');

    $collectionData = [
        'name'        => 'Updated Collection Name',
        'description' => 'Updated Collection Description',
    ];
    $collection     = UpdateCollection::make()->action($collection, $collectionData);

    expect($collection->name)->toBe('Updated Collection Name');

    return $collection;
})->depends('create collection');

test('update collection category', function ($collectionCategory) {
    expect($collectionCategory->name)->not->toBe('Updated Collection Category Name');

    $collectionCategoryData = [
        'name'        => 'Updated Collection Category Name',
        'description' => 'Updated Collection Category Description',
    ];
    $collectionCategory     = UpdateCollectionCategory::make()->action($collectionCategory, $collectionCategoryData);

    expect($collectionCategory->name)->toBe('Updated Collection Category Name');

    return $collectionCategory;
})->depends('create collection category');

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
        ->and($shop->stats->number_assets_type_charge)->toBe(1)
        ->and($shop->organisation->catalogueStats->number_assets_type_charge)->toBe(1)
        ->and($shop->group->catalogueStats->number_assets_type_charge)->toBe(1);


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
        ->and($updatedCharge->shop->stats->number_assets_type_charge)->toBe(1)
        ->and($updatedCharge->organisation->catalogueStats->number_assets_type_charge)->toBe(1)
        ->and($updatedCharge->group->catalogueStats->number_assets_type_charge)->toBe(1);


    return $updatedCharge;
})->depends('create charge');

test('add items to collection', function (Collection $collection) {
    $data = [
        'collections' => [2],
        'departments' => [1, 3],
        'families'    => [4],
        'products'    => [2]
    ];

    $collection = AttachModelsToCollection::make()->action($collection, $data);
    $collection->refresh();
    expect($collection)->toBeInstanceOf(Collection::class);

    return $collection;
})->depends('update collection');

test('remove items to collection', function (Collection $collection) {
    $collection = DetachModelFromCollection::make()->action($collection, [
        'department' => 1
    ]);
    $collection->refresh();

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->stats->number_departments)->toBe(1);

})->depends('add items to collection');

test('hydrate shops', function (Shop $shop) {
    HydrateShops::run($shop);
    $this->artisan('hydrate:shops')->assertExitCode(0);
})->depends('create shop');

test('hydrate departments', function (ProductCategory $department) {
    HydrateDepartments::run($department);
    $this->artisan('hydrate:departments')->assertExitCode(0);
})->depends('create department');

test('hydrate families', function (ProductCategory $family) {
    HydrateFamilies::run($family);
    $this->artisan('hydrate:families')->assertExitCode(0);
})->depends('create family');

test('hydrate products', function (Product $product) {
    HydrateProducts::run($product);
    $this->artisan('hydrate:products')->assertExitCode(0);
})->depends('create product');


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

test('products search', function () {
    $this->artisan('search:products')->assertExitCode(0);

    $product = Product::first();
    ReindexProductSearch::run($product);
    expect($product->universalSearch()->count())->toBe(1);
});

test('product categories search', function () {
    $this->artisan('search:product_categories')->assertExitCode(0);

    $productCategory = ProductCategory::first();
    ReindexProductCategorySearch::run($productCategory);
    expect($productCategory->universalSearch()->count())->toBe(1);
});

test('product collections search', function () {
    $this->artisan('search:collections')->assertExitCode(0);

    $collection = Collection::first();
    ReindexCollectionSearch::run($collection);
    expect($collection->universalSearch()->count())->toBe(1);
});

test('Billables: rentals search', function () {
    $this->artisan('search:rentals')->assertExitCode(0);

    StoreRental::make()->action(
        Shop::first(),
        [
            'code'        => 'MyFColl',
            'name'        => 'My first rental',
            'price'       => fake()->numberBetween(100, 2000),
            'unit'        => RentalUnitEnum::DAY->value,
        ]
    );

    $rental = Rental::first();
    ReindexRentalSearch::run($rental);
    expect($rental->universalSearch()->count())->toBe(1);
});

test('Billables: charges search', function () {
    $this->artisan('search:charges')->assertExitCode(0);

    $charge = Charge::first();
    ReindexChargeSearch::run($charge);
    expect($charge->universalSearch()->count())->toBe(1);
});

test('Billables: services search', function () {
    $this->artisan('search:services')->assertExitCode(0);

    $service = Service::first();
    ReindexServiceSearch::run($service);
    expect($service->universalSearch()->count())->toBe(1);
});

test('update shop setting', function ($shop) {
    $c = Country::first();
    $l = Language::first();

    $modelData = [
        'company_name' => 'new company name',
        'code' => "NEW",
        'name' => "new_name",
        'type' => ShopTypeEnum::DROPSHIPPING,
        'country_id' => $c->id,
        'language_id' => $l->id,
        'email' => "test@gmail.com",
        'phone' => "08912312313"

    ];
    $shop = UpdateShop::make()->action($shop, $modelData);
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

test('Billables: charges hydrator', function () {
    $this->artisan('hydrate:charges')->assertExitCode(0);
    HydrateCharge::run(Charge::first());
});

test('catalogue hydrator', function () {
    $this->artisan('hydrate -s cat')->assertExitCode(0);
});

test('billables hydrator', function () {
    $this->artisan('hydrate -s bil')->assertExitCode(0);
});
