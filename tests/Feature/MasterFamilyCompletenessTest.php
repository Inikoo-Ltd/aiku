<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\GetMasterProductChecklist;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterFamilyCompleteness;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterFamilyShopsStatus;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    createWebsite($this->shop);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->adminGuest->getUser());

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'MC'.substr(uniqid(), -6),
        'name' => 'Completeness Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MCD-'.uniqid(),
        'name' => 'completeness department',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MCF-'.uniqid(),
        'name' => 'completeness family',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);
});

test('master completeness lists what the master family provides and scores it', function () {
    $this->masterFamily->updateQuietly([
        'description_title'          => 'A title',
        'description'                => '<p>Top description</p>',
        'description_extra'          => '<p> </p>',
        'gr_vol_discount_quantity'   => 3,
        'gr_vol_discount_percentage' => 10,
        'faq'                        => [
            ['question' => 'First?', 'answer' => 'Yes'],
            ['question' => 'Second?', 'answer' => 'No'],
            ['question' => '', 'answer' => 'Ignored'],
        ],
    ]);

    $completeness = GetMasterFamilyCompleteness::run($this->masterFamily->fresh());

    $items = collect($completeness['items'])->keyBy('key');

    expect($completeness['total'])->toBe(11)
        ->and($items->where('is_complete', false)->keys()->sort()->values()->all())->toBe(['description_extra', 'meta_description', 'seo_image', 'sub_department'])
        ->and($items['faq']['label'])->toBe('FAQ (2 questions)')
        ->and($completeness['complete'])->toBe(7)
        ->and($completeness['score'])->toBe(64);

    $this->masterFamily->updateQuietly([
        'meta_description' => 'Handmade soaps for every skin type',
        'seo_image_url'    => 'https://cdn.example.com/share.png',
    ]);

    $items = collect(GetMasterFamilyCompleteness::run($this->masterFamily->fresh())['items'])->keyBy('key');

    expect($items['meta_description']['is_complete'])->toBeTrue()
        ->and($items['seo_image']['is_complete'])->toBeTrue();
});

test('shops status groups the shop families of the master family by state', function () {
    $this->shop->updateQuietly(['state' => ShopStateEnum::OPEN]);

    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'master_product_category_id', $this->masterFamily->id);
    $shopFamily = StoreProductCategory::make()->action($this->shop, $familyData);
    $shopFamily->updateQuietly(['state' => ProductCategoryStateEnum::DISCONTINUING]);

    $shopsStatus = GetMasterFamilyShopsStatus::run($this->masterFamily);

    expect($shopsStatus['total'])->toBe(1)
        ->and($shopsStatus['states'])->toBe([
            [
                'state' => ProductCategoryStateEnum::DISCONTINUING->value,
                'label' => 'Discontinuing',
                'count' => 1,
                'shops' => [$this->shop->code],
            ],
        ]);
});

test('families in shop page shows each shop family status, workflow and page score', function () {
    $this->shop->updateQuietly(['state' => ShopStateEnum::OPEN]);

    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'master_product_category_id', $this->masterFamily->id);
    $shopFamily = StoreProductCategory::make()->action($this->shop, $familyData);
    $shopFamily->updateQuietly(['state' => ProductCategoryStateEnum::DISCONTINUING, 'webpage_id' => null]);

    get(route('grp.masters.master_shops.show.master_families.families', [$this->masterFamily->masterShop->slug, $this->masterFamily->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Families')
            ->where('shops_status.states.0.state', ProductCategoryStateEnum::DISCONTINUING->value)
            ->where('queryBuilderProps.index.elementGroups.state.elements', [
                ProductCategoryStateEnum::DISCONTINUING->value => ['Discontinuing', 1],
            ])
            ->has('index.data', 1)
            ->where('index.data.0.shop_status', ['state' => ProductCategoryStateEnum::DISCONTINUING->value, 'label' => 'Discontinuing'])
            ->where('index.data.0.workflow.current', 1)
            ->where('index.data.0.workflow.steps.0', ['key' => 'created', 'label' => 'Page created', 'done' => false])
            ->has('index.data.0.workflow.steps', 4)
            ->where('index.data.0.page_score', null)
            ->where('index.data.0.public_url', null)
            ->etc());
});

test('families in shop page scores shop families that have a webpage', function () {
    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'master_product_category_id', $this->masterFamily->id);
    $shopFamily = StoreProductCategory::make()->action($this->shop, $familyData);
    $shopFamily->webpage ?? StoreProductCategoryWebpage::make()->action($shopFamily);

    get(route('grp.masters.master_shops.show.master_families.families', [$this->masterFamily->masterShop->slug, $this->masterFamily->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Families')
            ->has('index.data', 1)
            ->whereType('index.data.0.page_score', 'integer')
            ->etc());
});

test('edit master family page shows the master content, parent options and checklist, and saves fields together', function () {
    $masterFamily     = $this->masterFamily->fresh();
    $masterDepartment = $masterFamily->masterDepartment;

    get(route('grp.masters.master_shops.show.master_departments.show.master_families.edit', [$masterFamily->masterShop->slug, $masterDepartment->slug, $masterFamily->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Masters/EditMasterFamily')
            ->where('masterFamily.code', $masterFamily->code)
            ->where('masterFamily.master_department_id', $masterDepartment->id)
            ->where('departments', fn ($departments) => collect($departments)->contains('id', $masterDepartment->id))
            ->where('completeness.total', 11)
            ->where('masterFamily.meta_description', null)
            ->where('masterFamily.seo_image', null)
            ->where('familiesRoute.name', 'grp.masters.master_shops.show.master_departments.show.master_families.families')
            ->where('updateRoute.name', 'grp.models.master_product_category.update')
            ->etc());

    $this->patch(route('grp.models.master_product_category.update', $masterFamily->id), [
        'name'              => 'Renamed family',
        'description_title' => 'New title',
        'master_department_or_master_sub_department_id' => $masterDepartment->id,
    ])->assertSessionHasNoErrors();

    $masterFamily->refresh();

    expect($masterFamily->name)->toBe('Renamed family')
        ->and($masterFamily->description_title)->toBe('New title')
        ->and($masterFamily->master_department_id)->toBe($masterDepartment->id);
});

test('master family meta description and share image are saved like the webpage share image', function () {
    $masterFamily = $this->masterFamily->fresh();

    $this->patch(route('grp.models.master_product_category.update', $masterFamily->id), [
        'meta_description' => 'Handmade soaps for every skin type',
        'seo_image_url'    => 'cdn.example.com/share.png',
    ])->assertSessionHasNoErrors();

    $masterFamily->refresh();

    expect($masterFamily->meta_description)->toBe('Handmade soaps for every skin type')
        ->and($masterFamily->seo_image_url)->toBe('https://cdn.example.com/share.png');

    $this->patch(route('grp.models.master_product_category.update', $masterFamily->id), [
        'seo_image' => UploadedFile::fake()->image('share.png', 1200, 630),
    ])->assertSessionHasNoErrors();

    $masterFamily->refresh();

    expect($masterFamily->seo_image_id)->not->toBeNull()
        ->and($masterFamily->seo_image_url)->toBeNull()
        ->and($masterFamily->seoImage)->not->toBeNull()
        ->and($masterFamily->images()->whereKey($masterFamily->seo_image_id)->exists())->toBeFalse();
});

test('master family meta description and share image cascade to the webpages of shop families following the master', function () {
    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'master_product_category_id', $this->masterFamily->id);
    $shopFamily = StoreProductCategory::make()->action($this->shop, $familyData);
    $webpage    = $shopFamily->webpage ?? StoreProductCategoryWebpage::make()->action($shopFamily);

    $settings = $this->shop->settings;
    data_set($settings, 'catalog.family_follow_master', false);
    $this->shop->update(['settings' => $settings]);

    Translate::mock()->shouldReceive('handle')->never();

    $this->patch(route('grp.models.master_product_category.update', $this->masterFamily->id), [
        'meta_description' => 'Handmade soaps for every skin type',
        'seo_image_alt'    => 'Soap bars on a wooden tray',
    ])->assertSessionHasNoErrors();

    expect($webpage->fresh()->description)->not->toBe('Handmade soaps for every skin type')
        ->and(data_get($webpage->fresh()->seo_data, 'image_alt'))->toBeNull()
        ->and($this->masterFamily->fresh()->seo_image_alt)->toBe('Soap bars on a wooden tray');

    data_set($settings, 'catalog.family_follow_master', true);
    $this->shop->update(['settings' => $settings]);

    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (?string $text) => 'Translated: '.$text);

    $this->patch(route('grp.models.master_product_category.update', $this->masterFamily->id), [
        'meta_description' => 'Soaps made by hand',
        'seo_image'        => UploadedFile::fake()->image('share.png', 1200, 630),
    ])->assertSessionHasNoErrors();

    $masterFamily = $this->masterFamily->fresh();
    $webpage->refresh();

    expect($webpage->description)->toBe('Translated: Soaps made by hand')
        ->and($webpage->seo_image_id)->toBe($masterFamily->seo_image_id)
        ->and($webpage->seo_image_url)->toBeNull();

    $this->patch(route('grp.models.master_product_category.update', $this->masterFamily->id), [
        'seo_image_url' => 'https://cdn.example.com/share.png',
    ])->assertSessionHasNoErrors();

    expect($webpage->fresh()->seo_image_url)->toBe('https://cdn.example.com/share.png');

    $this->patch(route('grp.models.master_product_category.update', $this->masterFamily->id), [
        'seo_image_alt' => 'Three soap bars on a wooden tray',
    ])->assertSessionHasNoErrors();

    expect(data_get($webpage->fresh()->seo_data, 'image_alt'))->toBe('Translated: Three soap bars on a wooden tray');

    $this->patch(route('grp.models.master_product_category.update', $this->masterFamily->id), [
        'seo_image_alt' => null,
    ])->assertSessionHasNoErrors();

    expect(data_get($webpage->fresh()->seo_data, 'image_alt'))->toBeNull();
});

test('master product meta description and share image are saved like the webpage share image and counted in its checklist', function () {
    $tradeUnit = StoreTradeUnit::make()->action(group(), array_merge(TradeUnit::factory()->definition(), [
        'code' => 'SEO-'.substr(uniqid(), -6),
        'name' => 'Share image trade unit',
    ]));

    $masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'SEO-'.substr(uniqid(), -6),
        'name'        => 'share image product',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 1]],
    ]);
    $catalogueImageId = $masterAsset->image_id;

    $checklistItems = fn () => collect(GetMasterProductChecklist::run(GetMasterProductChecklist::make()->checklistRow($masterAsset))['items'])->keyBy('key');

    expect($checklistItems()['meta_description']['done'])->toBeFalse()
        ->and($checklistItems()['seo_image']['done'])->toBeFalse();

    $this->patch(route('grp.models.master_asset.update', $masterAsset->id), [
        'meta_description' => 'A lamp that glows like driftwood embers',
        'seo_image_url'    => 'cdn.example.com/lamp.png',
    ])->assertSessionHasNoErrors();

    $masterAsset->refresh();

    expect($masterAsset->meta_description)->toBe('A lamp that glows like driftwood embers')
        ->and($masterAsset->seo_image_url)->toBe('https://cdn.example.com/lamp.png')
        ->and($checklistItems()['meta_description']['done'])->toBeTrue()
        ->and($checklistItems()['seo_image']['done'])->toBeTrue();

    $this->patch(route('grp.models.master_asset.update', $masterAsset->id), [
        'seo_image' => UploadedFile::fake()->image('lamp.png', 1200, 630),
    ])->assertSessionHasNoErrors();

    $masterAsset->refresh();

    expect($masterAsset->seo_image_id)->not->toBeNull()
        ->and($masterAsset->seo_image_url)->toBeNull()
        ->and($masterAsset->image_id)->toBe($catalogueImageId)
        ->and($masterAsset->images()->whereKey($masterAsset->seo_image_id)->exists())->toBeFalse();
});
