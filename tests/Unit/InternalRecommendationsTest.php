<?php

use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockInternalRecommendations;
use App\Actions\Web\WebBlock\Workshop\GetWebBlockInternalRecommendations;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

test('internal recommendations use product ids and preserve their catalogue scope', function () {
    $product = new Product();
    $product->forceFill(['id' => 17, 'department_id' => 23]);
    $webpage = new Webpage();
    $webpage->setRelation('model', $product);

    $block = ['type' => 'luigi-item-alternatives-1'];
    $result = (new GetIrisWebBlockInternalRecommendations())->handle($webpage, $block);

    expect(data_get($result, 'web_block.layout.data.fieldValue.product'))->toBe(['id' => 17])
        ->and(data_get($result, 'web_block.layout.data.fieldValue.recommendation_scope'))->toBe(['department_id' => 23]);

    $workshop = (new GetWebBlockInternalRecommendations())->handle($webpage, $block);

    expect(data_get($workshop, 'web_block.layout.data.fieldValue.product'))->toBe(['id' => 17]);
});

test('legacy trends blocks stay hidden on family pages', function () {
    $family = new ProductCategory();
    $family->forceFill(['id' => 42, 'type' => ProductCategoryTypeEnum::FAMILY]);
    $webpage = new Webpage();
    $webpage->setRelation('model', $family);

    $action = new GetIrisWebBlockInternalRecommendations();

    expect($action->handle($webpage, ['type' => 'luigi-trends-1']))->toBeNull()
        ->and(data_get(
            $action->handle($webpage, ['type' => 'luigi-last-seen-1']),
            'web_block.layout.data.fieldValue.recommendation_scope'
        ))->toBe(['family_id' => 42]);
});

test('last seen recommendations still expose internal product data and prices', function () {
    $product = (object) [
        'id' => 17,
        'code' => 'TEST-17',
        'name' => 'Test product',
        'available_quantity' => 12,
        'webpage_id' => 5,
        'last_seen_at' => null,
        'price' => 20,
        'rrp' => 40,
        'units' => 2,
        'unit' => 'piece',
        'web_images' => ['main' => ['original' => ['original' => '/product.jpg']]],
        'url' => '/test-product',
        'offers_data' => [],
        'product_offers_data' => '{}',
    ];

    $data = (new IrisProductLastSeenResource($product))->resolve();

    expect($data)->toMatchArray([
        'id' => 17,
        'code' => 'TEST-17',
        'name' => 'Test product',
        'url' => '/test-product',
        'price' => 20,
        'stock' => 12,
    ])->and($data['price_per_unit'])->toEqual(10);
});
