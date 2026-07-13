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
use App\Actions\Catalogue\Collection\StoreCollectionWebpage;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\RefreshGrpAssetUrls;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\WithIrisGetWebpageWebBlocks;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

const PRODUCT_WEBPAGE_BLOCKS = [
    'product',
    'product-1',
    'product-2',
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
    'faq-department',
    'top-families',
    'sub-departments-1',
    'sub-departments-2',
    'sub-departments-3',
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
