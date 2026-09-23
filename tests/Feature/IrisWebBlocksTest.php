<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/*
 * Renders EVERY webpage-scope web block type through the same server pipeline iris
 * uses (StoreModelHasWebBlock default layout → PublishWebpage → getIrisWebBlocks →
 * RefreshGrpAssetUrls), so a per-type handler that fatals (missing import, bad array
 * path, broken resource) fails here instead of in production.
 *
 * Model-bound block types are attached to a webpage of the model they read
 * (product / family / department / sub-department); everything else goes on the
 * storefront webpage.
 */

use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Catalogue\Collection\StoreCollectionWebpage;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\UpdateFamilyDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateFamilySubDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\RefreshGrpAssetUrls;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\WithIrisGetWebpageWebBlocks;
use App\Actions\Web\WebBlock\Iris\GetWebBlockProduct as IrisGetWebBlockProduct;
use App\Actions\Web\WebBlock\Traits\WithFamiliesQuery;
use App\Actions\Web\WebBlock\Workshop\GetWebBlockProduct as WorkshopGetWebBlockProduct;
use App\Actions\Web\Website\GetWebsiteWorkshopProduct;
use App\Enums\Catalogue\ProductCategory\FamilyCustomizeEnum;
use App\Enums\Catalogue\ProductCategory\FamilyStorageConditionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const PRODUCT_WEBPAGE_BLOCKS = [
    'product',
    'product-1',
    'product-2',
    'product-3',
    'recommendation-customer-recently-bought-1',
];

const FAMILY_WEBPAGE_BLOCKS = [
    'family',
    'family-1',
    'family-2',
    'family-2-extra-description',
    'family-3',
    'family-3-extra-description',
    'products-1',
    'products-2',
];

const DEPARTMENT_WEBPAGE_BLOCKS = [
    'department',
    'department-description-1',
    'department-description-2',
    'department-description-3',
    'faq-department',
    'top-families',
    'sub-departments-1',
    'sub-departments-2',
    'sub-departments-3',
    'sub-departments-4',
    'families-4',
];

const SUB_DEPARTMENT_WEBPAGE_BLOCKS = [
    'sub-department-1',
    'sub-department-description-1',
];

const COLLECTION_WEBPAGE_BLOCKS = [
    'collection-1',
    'collection-description-1',
    'collections-1',
];

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);
    $this->website->update(['status' => true]);
});

test('every webpage web block type renders through the iris pipeline', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;
    $department  = $product->department;

    $subDepartmentData = ProductCategory::factory()->definition();
    data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
    $subDepartment = StoreProductCategory::make()->action($department, $subDepartmentData);

    $collection = StoreCollection::make()->action(
        $this->shop,
        [
            'code'        => 'BlockCov',
            'name'        => 'Web block coverage collection',
            'description' => 'Web block coverage collection description',
        ]
    );

    $webpages = [
        'storefront'     => StoreWebpage::make()->action($this->website->storefront, Webpage::factory()->definition()),
        'product'        => StoreProductWebpage::make()->action($product),
        'family'         => StoreProductCategoryWebpage::make()->action($family),
        'department'     => StoreProductCategoryWebpage::make()->action($department),
        'sub_department' => StoreProductCategoryWebpage::make()->action($subDepartment),
        'collection'     => StoreCollectionWebpage::make()->handle($collection),
    ];

    $webpageFor = function (string $code) use ($webpages): Webpage {
        return match (true) {
            in_array($code, PRODUCT_WEBPAGE_BLOCKS)        => $webpages['product'],
            in_array($code, FAMILY_WEBPAGE_BLOCKS)         => $webpages['family'],
            in_array($code, DEPARTMENT_WEBPAGE_BLOCKS)     => $webpages['department'],
            in_array($code, SUB_DEPARTMENT_WEBPAGE_BLOCKS) => $webpages['sub_department'],
            in_array($code, COLLECTION_WEBPAGE_BLOCKS)     => $webpages['collection'],
            default                                        => $webpages['storefront'],
        };
    };

    $webBlockTypes = $this->website->group->webBlockTypes()->where('scope', 'webpage')->orderBy('code')->get();
    expect($webBlockTypes->count())->toBeGreaterThan(0);

    foreach ($webBlockTypes as $position => $webBlockType) {
        StoreModelHasWebBlock::make()->action(
            $webpageFor($webBlockType->code),
            [
                'web_block_type_id' => $webBlockType->id,
                'position'          => $position,
            ]
        );
    }

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $failures      = [];
    $renderedTypes = [];
    foreach ($webpages as $webpage) {
        $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'web block coverage test']);

        foreach (Arr::get($webpage->published_layout, 'web_blocks', []) as $key => $publishedBlock) {
            $type            = Arr::get($publishedBlock, 'type', 'unknown');
            $renderedTypes[] = $type;
            foreach ([false, true] as $isLoggedIn) {
                try {
                    $parsed = $renderer->getIrisWebBlocks($webpage, [$key => $publishedBlock], $isLoggedIn);
                    RefreshGrpAssetUrls::run($parsed);
                } catch (Throwable $e) {
                    $failures[] = sprintf(
                        '%s (loggedIn=%s): %s: %s',
                        $type,
                        $isLoggedIn ? 'yes' : 'no',
                        get_class($e),
                        $e->getMessage()
                    );
                }
            }
        }
    }

    expect($failures)->toBeEmpty(implode("\n", $failures))
        ->and(array_unique($renderedTypes))->toHaveCount($webBlockTypes->count());
});

test('family extra description block exposes the family customize options', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    UpdateProductCategory::make()->action($family, [
        'customize_option' => [
            [
                'key'       => 'packaging',
                'available' => true,
                'moq'       => '£500+',
                'notes'     => 'Multiple packaging formats and sizes.',
            ],
            [
                'key'       => 'fragrance',
                'available' => false,
                'moq'       => '',
                'notes'     => '',
            ],
        ],
    ]);

    $webpage       = StoreProductCategoryWebpage::make()->action($family);
    $webBlockType  = $this->website->group->webBlockTypes()->where('code', 'family-2-extra-description')->firstOrFail();

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'family customize option test']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $publishedBlocks = collect(Arr::get($webpage->published_layout, 'web_blocks', []))
        ->filter(fn ($block) => Arr::get($block, 'type') === 'family-2-extra-description')
        ->all();

    expect($publishedBlocks)->not->toBeEmpty();

    $parsed = $renderer->getIrisWebBlocks($webpage, $publishedBlocks, false);

    $customizeOptions = Arr::get(Arr::first($parsed), 'structure.family.customize_option');

    expect($customizeOptions)->toHaveCount(count(FamilyCustomizeEnum::cases()));

    $rowsByKey = collect($customizeOptions)->keyBy('key');

    expect($rowsByKey->get('packaging'))
        ->toMatchArray([
            'key'       => 'packaging',
            'icon'      => 'fal fa-box',
            'available' => true,
            'moq'       => '£500+',
            'notes'     => 'Multiple packaging formats and sizes.',
        ])
        ->and($rowsByKey->get('fragrance')['available'])->toBeFalse()
        ->and($rowsByKey->get('colour'))
        ->toMatchArray([
            'available' => false,
            'moq'       => '',
            'notes'     => '',
        ]);
});

test('family extra description block flags families of the aroma organisation', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    $webpage      = StoreProductCategoryWebpage::make()->action($family);
    $webBlockType = $this->website->group->webBlockTypes()->where('code', 'family-2-extra-description')->firstOrFail();

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'aroma organisation flag test']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $renderFamilyBlock = function () use ($renderer, $webpage) {
        $publishedBlocks = collect(Arr::get($webpage->published_layout, 'web_blocks', []))
            ->filter(fn ($block) => Arr::get($block, 'type') === 'family-2-extra-description')
            ->all();

        $parsed = $renderer->getIrisWebBlocks($webpage->fresh(), $publishedBlocks, true);

        return Arr::get(Arr::first($parsed), 'structure.family');
    };

    expect(Arr::get($renderFamilyBlock(), 'is_aroma_organisation'))->toBeFalse();

    $family->organisation->update(['slug' => 'aroma']);

    expect(Arr::get($renderFamilyBlock(), 'is_aroma_organisation'))->toBeTrue();
});

test('family extra description block exposes the family storage options', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    UpdateProductCategory::make()->action($family, [
        'storage_conditions'  => [
            ['key' => 'storage', 'value' => 'Store in a cool, dry place.'],
            ['key' => 'shelf_life', 'value' => '24 months from manufacture.'],
        ],
        'storage_temperature' => '15°C - 25°C',
        'storage_guidelines'  => [
            ['text' => 'Keep products in their original packaging.'],
            ['text' => 'Protect from heat and sunlight.'],
        ],
    ]);

    $webpage      = StoreProductCategoryWebpage::make()->action($family);
    $webBlockType = $this->website->group->webBlockTypes()->where('code', 'family-2-extra-description')->firstOrFail();

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'family storage option test']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $publishedBlocks = collect(Arr::get($webpage->published_layout, 'web_blocks', []))
        ->filter(fn ($block) => Arr::get($block, 'type') === 'family-2-extra-description')
        ->all();

    $parsed        = $renderer->getIrisWebBlocks($webpage, $publishedBlocks, true);
    $storageOption = Arr::get(Arr::first($parsed), 'structure.family.storage_option');

    expect(Arr::get($storageOption, 'storage_temperature'))->toBe('15°C - 25°C')
        ->and(Arr::get($storageOption, 'storage_guidelines'))->toHaveCount(2)
        ->and(Arr::get($storageOption, 'storage_conditions'))
        ->toHaveCount(count(FamilyStorageConditionEnum::cases()));

    $conditionsByKey = collect(Arr::get($storageOption, 'storage_conditions'))->keyBy('key');

    expect($conditionsByKey->get('storage'))
        ->toMatchArray([
            'key'   => 'storage',
            'label' => 'Storage',
            'value' => 'Store in a cool, dry place.',
        ])
        ->and($conditionsByKey->get('shelf_life')['value'])->toBe('24 months from manufacture.')
        ->and($conditionsByKey->get('after_opening')['value'])->toBe('');
});

test('family extra description block exposes the labeling guide download route', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    UpdateProductCategory::make()->action($family, [
        'labeling_guide_file' => UploadedFile::fake()->create('labeling-guide.pdf', 12, 'application/pdf'),
    ]);

    $webpage      = StoreProductCategoryWebpage::make()->action($family);
    $webBlockType = $this->website->group->webBlockTypes()->where('code', 'family-2-extra-description')->firstOrFail();

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'labeling guide test']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $publishedBlocks = collect(Arr::get($webpage->published_layout, 'web_blocks', []))
        ->filter(fn ($block) => Arr::get($block, 'type') === 'family-2-extra-description')
        ->all();

    $parsed        = $renderer->getIrisWebBlocks($webpage, $publishedBlocks, true);
    $labelingGuide = Arr::get(Arr::first($parsed), 'structure.family.labeling_guide');

    $media = $family->labelingGuide();

    expect($media)->not->toBeNull()
        ->and(Arr::get($labelingGuide, 'route.name'))->toBe('iris.attach.download')
        ->and(Arr::get($labelingGuide, 'route.parameters.media'))->toBe($media->ulid)
        ->and(route(Arr::get($labelingGuide, 'route.name'), Arr::get($labelingGuide, 'route.parameters')))
        ->toContain($media->ulid);
});

test('family extra description block returns a null labeling guide when no file is attached', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;

    $webpage      = StoreProductCategoryWebpage::make()->action($family);
    $webBlockType = $this->website->group->webBlockTypes()->where('code', 'family-2-extra-description')->firstOrFail();

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'labeling guide null test']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $publishedBlocks = collect(Arr::get($webpage->published_layout, 'web_blocks', []))
        ->filter(fn ($block) => Arr::get($block, 'type') === 'family-2-extra-description')
        ->all();

    $parsed = $renderer->getIrisWebBlocks($webpage, $publishedBlocks, true);
    $family = Arr::get(Arr::first($parsed), 'structure.family');

    expect($family)->toHaveKey('labeling_guide')
        ->and(Arr::get($family, 'labeling_guide'))->toBeNull();
});

test('workshop product web block keeps the description tabs alongside the website product layout', function () {
    [, $product] = createProduct($this->shop);

    $this->website->update([
        'published_layout' => [
            'product' => [
                'data' => [
                    'fieldValue' => [
                        'setting' => ['product_specs' => true],
                    ],
                ],
            ],
        ],
    ]);

    $webpage = StoreProductWebpage::make()->action($product);

    $parsed = WorkshopGetWebBlockProduct::run($webpage, ['type' => 'product-3']);

    $fieldValue = Arr::get($parsed, 'web_block.layout.data.fieldValue');

    expect(Arr::get($fieldValue, 'setting.product_specs'))->toBeTrue()
        ->and(Arr::get($fieldValue, 'tabs.description'))->toBe($product->description)
        ->and(Arr::get($fieldValue, 'tabs'))->toHaveKeys([
            'customize_option',
            'labeling_guide',
            'storage_option',
            'is_aroma_organisation',
            'marketing_material_route',
            'faq',
        ]);
});

test('website product workshop layout exposes the description tabs', function () {
    [, $product] = createProduct($this->shop);

    $snapshot = StoreWebsiteSnapshot::make()->action($this->website, [
        'scope'  => SnapshotScopeEnum::PRODUCT,
        'layout' => [
            'product' => [
                'code' => 'product-3',
                'data' => [
                    'fieldValue' => [
                        'setting' => ['product_specs' => true],
                    ],
                ],
            ],
        ],
    ]);

    $this->website->update(['unpublished_product_snapshot_id' => $snapshot->id]);

    $workshop = GetWebsiteWorkshopProduct::run($this->website->fresh(), $product);

    $tabs = Arr::get($workshop, 'layout.data.fieldValue.tabs');

    expect($tabs)->not->toBeNull()
        ->and(Arr::get($tabs, 'description'))->toBe($product->description)
        ->and(Arr::get($workshop, 'layout.data.fieldValue.product.id'))->toBe($product->id);
});

test('product web blocks expose the family extra description layout as the description tabs style', function () {
    [, $product] = createProduct($this->shop);

    $this->website->update([
        'published_layout' => [
            'product' => [
                'data' => [
                    'fieldValue' => [
                        'setting' => ['product_specs' => true],
                    ],
                ],
            ],
            'family_description' => [
                'family-2' => [
                    'fieldValue' => ['container' => ['properties' => ['background' => ['color' => '#ffffff']]]],
                ],
                'family-2-extra-description' => [
                    'fieldValue' => [
                        'storage'   => ['title' => 'Storage & Shelf Life'],
                        'container' => ['properties' => ['background' => ['color' => '#f4f4f4']]],
                    ],
                ],
            ],
        ],
    ]);

    $webpage = StoreProductWebpage::make()->action($product);

    $workshopFieldValue = Arr::get(WorkshopGetWebBlockProduct::run($webpage, ['type' => 'product-3']), 'web_block.layout.data.fieldValue');
    $irisFieldValue     = Arr::get(IrisGetWebBlockProduct::run($webpage, ['type' => 'product-3']), 'structure');

    foreach ([$workshopFieldValue, $irisFieldValue] as $fieldValue) {
        expect(Arr::get($fieldValue, 'setting.product_specs'))->toBeTrue()
            ->and(Arr::get($fieldValue, 'tabs.description'))->toBe($product->description)
            ->and(Arr::get($fieldValue, 'tabs_style.storage.title'))->toBe('Storage & Shelf Life')
            ->and(Arr::get($fieldValue, 'tabs_style.container.properties.background.color'))->toBe('#f4f4f4');
    }
});

test('product web blocks return a null description tabs style when no family extra description is published', function () {
    [, $product] = createProduct($this->shop);

    $this->website->update([
        'published_layout' => [
            'family_description' => [
                'family-1' => [
                    'fieldValue' => ['container' => ['properties' => null]],
                ],
            ],
        ],
    ]);

    $webpage = StoreProductWebpage::make()->action($product);

    $fieldValue = Arr::get(WorkshopGetWebBlockProduct::run($webpage, ['type' => 'product-3']), 'web_block.layout.data.fieldValue');

    expect($fieldValue)->toHaveKey('tabs_style')
        ->and(Arr::get($fieldValue, 'tabs_style'))->toBeNull();
});

test('website product workshop layout exposes the family extra description style', function () {
    [, $product] = createProduct($this->shop);

    $snapshot = StoreWebsiteSnapshot::make()->action($this->website, [
        'scope'  => SnapshotScopeEnum::PRODUCT,
        'layout' => [
            'product' => [
                'code' => 'product-3',
                'data' => [
                    'fieldValue' => [
                        'setting' => ['product_specs' => true],
                    ],
                ],
            ],
        ],
    ]);

    $this->website->update([
        'unpublished_product_snapshot_id' => $snapshot->id,
        'published_layout'                => [
            'family_description' => [
                'family-2-extra-description' => [
                    'fieldValue' => ['storage' => ['title' => 'Storage & Shelf Life']],
                ],
            ],
        ],
    ]);

    $workshop = GetWebsiteWorkshopProduct::run($this->website->fresh(), $product);

    expect(Arr::get($workshop, 'layout.data.fieldValue.tabs_style.storage.title'))->toBe('Storage & Shelf Life');
});

test('register dashboard 2 block keeps its default text, style and hero image through the iris pipeline', function () {
    $webBlockType = $this->website->group->webBlockTypes()->where('code', 'register-dashboard-2')->firstOrFail();
    $webpage      = StoreWebpage::make()->action($this->website->storefront, Webpage::factory()->definition());

    StoreModelHasWebBlock::make()->action($webpage, [
        'web_block_type_id' => $webBlockType->id,
        'position'          => 0,
    ]);

    $webpage = PublishWebpage::make()->action($webpage, ['comment' => 'register dashboard 2']);

    $renderer = new class () {
        use WithIrisGetWebpageWebBlocks;
    };

    $parsed    = $renderer->getIrisWebBlocks($webpage, Arr::get($webpage->published_layout, 'web_blocks', []), false);
    $structure = Arr::get(array_values($parsed)[0], 'structure');

    expect(array_values($parsed)[0]['type'])->toBe('register-dashboard-2')
        ->and(Arr::get($structure, 'card.button.color'))->toBe('#e87928')
        ->and(Arr::get($structure, 'hero.benefit_border_color'))->toBe('#c8c6c2')
        ->and(Arr::get($structure, 'hero.title'))->toBe('Create your Ancient Wisdom wholesale account')
        ->and(Arr::get($structure, 'hero.benefits'))->toHaveCount(3)
        ->and(Arr::get($structure, 'card.checks'))->toHaveCount(3)
        ->and(Arr::get($structure, 'card.checks.0.icon'))->toBe(['fal', 'check'])
        ->and(Arr::get($structure, 'card.button.link.href'))->toBe('/app/registration-form')
        ->and(Arr::get($structure, 'card.google.show'))->toBeTrue()
        ->and(Arr::get($structure, 'faq.items'))->toHaveCount(6)
        ->and(public_path(ltrim(Arr::get($structure, 'hero.image_url'), '/')))->toBeFile();
});

test('changing a family drops the cache of the department and sub-department pages that list it', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;
    $department  = $product->department;

    $subDepartmentData = ProductCategory::factory()->definition();
    data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
    $subDepartment = StoreProductCategory::make()->action($department, $subDepartmentData);

    $otherDepartmentData = ProductCategory::factory()->definition();
    data_set($otherDepartmentData, 'type', ProductCategoryTypeEnum::DEPARTMENT->value);
    $otherDepartment = StoreProductCategory::make()->action($this->shop, $otherDepartmentData);

    UpdateFamilySubDepartment::make()->action($family, ['sub_department_id' => $subDepartment->id]);
    $family->refresh();

    $familyWebpage        = StoreProductCategoryWebpage::make()->action($family);
    $departmentWebpage    = StoreProductCategoryWebpage::make()->action($department);
    $subDepartmentWebpage = StoreProductCategoryWebpage::make()->action($subDepartment);

    expect(BreakWebpageCache::make()->getFamilyListingWebpages($family)->pluck('id'))
        ->toContain($departmentWebpage->id)
        ->toContain($subDepartmentWebpage->id);

    $cacheKey = fn (Webpage $webpage, string $side): string => config('iris.cache.webpage.prefix')
        .'_'.$webpage->website_id.'_'.$side.'_'.$webpage->id;

    $warmListings = function (Webpage ...$listings) use ($cacheKey) {
        foreach ($listings as $listing) {
            foreach (['in', 'out'] as $side) {
                Cache::put($cacheKey($listing, $side), 'families as they were');
            }
        }
    };

    $expectRebuilt = function (string $after, Webpage ...$listings) use ($cacheKey) {
        foreach ($listings as $listing) {
            foreach (['in', 'out'] as $side) {
                expect(Cache::has($cacheKey($listing, $side)))
                    ->toBeFalse($listing->url.' ('.$side.') still cached after '.$after);
            }
        }
    };

    $warmListings($departmentWebpage, $subDepartmentWebpage);
    PublishWebpage::make()->action($familyWebpage, ['comment' => 'family goes live']);
    $expectRebuilt('publishing the family webpage', $departmentWebpage, $subDepartmentWebpage);

    PublishWebpage::make()->action($departmentWebpage, ['comment' => 'department goes live']);

    $warmListings($departmentWebpage, $subDepartmentWebpage);
    CloseWebpage::make()->action($familyWebpage, [
        'redirect_type' => RedirectTypeEnum::PERMANENT->value,
        'to_webpage_id' => $departmentWebpage->id,
    ]);
    $expectRebuilt('closing the family webpage', $departmentWebpage, $subDepartmentWebpage);

    $warmListings($departmentWebpage, $subDepartmentWebpage);
    ReopenWebpage::run($familyWebpage);
    $expectRebuilt('reopening the family webpage', $departmentWebpage, $subDepartmentWebpage);

    $warmListings($departmentWebpage, $subDepartmentWebpage);
    UpdateFamilyDepartment::make()->action($family, ['department_id' => $otherDepartment->id]);
    $expectRebuilt('moving the family to another department', $departmentWebpage, $subDepartmentWebpage);
});

test('families that sold the same are listed newest first', function () {
    [, $product] = createProduct($this->shop);

    $subDepartmentData = ProductCategory::factory()->definition();
    data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
    $subDepartment = StoreProductCategory::make()->action($product->department, $subDepartmentData);

    $subDepartmentWebpage = StoreProductCategoryWebpage::make()->action($subDepartment);

    $families = [];
    foreach (['older' => 20, 'newer' => 2] as $label => $daysAgo) {
        $familyData = ProductCategory::factory()->definition();
        data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        $family = StoreProductCategory::make()->action($subDepartment, $familyData);

        DB::table('product_categories')->where('id', $family->id)->update([
            'created_at'      => now()->subDays($daysAgo),
            'state'           => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE->value,
            'show_in_website' => true,
        ]);

        PublishWebpage::make()->action(
            StoreProductCategoryWebpage::make()->action($family),
            ['comment' => 'family goes live']
        );

        $families[$label] = $family->fresh();
    }

    $runner = new class () {
        use WithFamiliesQuery;
    };

    $listed = $runner->getFamilyList($subDepartmentWebpage, ['product_categories.code'])
        ->addSelect(DB::raw('yearly_sales.total_sales'))
        ->get();

    expect($listed->pluck('total_sales')->unique())->toHaveCount(1)
        ->and($listed->pluck('code')->all())
        ->toBe([$families['newer']->code, $families['older']->code]);
});

test('cached family product list carries the same shop-wide offer prices as the logged-in list', function () {
    [, $product] = createProduct($this->shop);

    DB::table('products')->where('id', $product->id)->update([
        'price'              => 20,
        'available_quantity' => 10,
        'offers_data'        => json_encode(['number_offers' => 1, 'best_percentage_off' => ['percentage_off' => 0.1]]),
    ]);

    PublishWebpage::make()->action(StoreProductWebpage::make()->action($product), ['comment' => 'product goes live']);

    $row = collect(GetIrisProductsInProductCategory::run(productCategory: $product->family)->items())
        ->firstWhere('id', $product->id);

    expect($row)->not->toBeNull();

    $cached    = (new IrisProductsInWebpageResource($row))->toArray(request());
    $loggedIn  = (new IrisAuthenticatedProductsInWebpageResource($row))->toArray(request());
    $offerKeys = [
        'family_id',
        'is_coming_soon',
        'is_golden_product',
        'variant',
        'product_offers_data',
        'discounted_price',
        'discounted_price_per_unit',
        'discounted_profit',
        'discounted_profit_per_unit',
        'discounted_margin',
        'discounted_percentage',
        'step_discount',
    ];

    expect(Arr::only($cached, $offerKeys))->toBe(Arr::only($loggedIn, $offerKeys))
        ->and($cached)->toHaveKeys($offerKeys)
        ->and($cached['discounted_price'])->toEqual(18.0)
        ->and($cached['product_offers_data']['number_offers'])->toBe(1)
        ->and($cached['family_id'])->toBe($product->family_id);
});
