<?php

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;

it('gives a dropshipping shop its own blog categories', function () {
    expect(WebpageSubTypeEnum::blogCategoryValues(ShopTypeEnum::DROPSHIPPING))
        ->toBe(['integrations_guides', 'dropshipping_guides', 'product_guides']);
});

it('keeps the catalogue blog categories for every other shop type', function (?ShopTypeEnum $shopType) {
    expect(WebpageSubTypeEnum::blogCategoryValues($shopType))
        ->toBe(['newsletters', 'product_guides', 'business_tips']);
})->with([
    null,
    ShopTypeEnum::B2B,
    ShopTypeEnum::B2C,
    ShopTypeEnum::FULFILMENT,
    ShopTypeEnum::EXTERNAL,
]);

it('labels every blog category of every shop type', function () {
    $labels = WebpageSubTypeEnum::labels();

    foreach (WebpageSubTypeEnum::allBlogCategories() as $category) {
        expect($labels)->toHaveKey($category->value)
            ->and($category->blogCategoryUrl())->not->toBeNull();
    }
});

it('offers the blog categories of the shop type with their label', function () {
    expect(WebpageSubTypeEnum::blogCategoriesWithLabel(ShopTypeEnum::DROPSHIPPING))->toBe([
        ['value' => 'integrations_guides', 'label' => 'Integrations Guides'],
        ['value' => 'dropshipping_guides', 'label' => 'Dropshipping Guides'],
        ['value' => 'product_guides', 'label' => 'Product Guides'],
    ]);
});

it('reads the new dropshipping sub types as their own blog category', function () {
    expect(WebpageSubTypeEnum::resolveBlogCategory('integrations_guides'))
        ->toBe(WebpageSubTypeEnum::INTEGRATIONS_GUIDES)
        ->and(WebpageSubTypeEnum::resolveBlogCategory('dropshipping_guides'))
        ->toBe(WebpageSubTypeEnum::DROPSHIPPING_GUIDES);
});

it('resolves the legacy sub types the same way as before', function () {
    expect(WebpageSubTypeEnum::resolveBlogCategory('blog'))->toBe(WebpageSubTypeEnum::PRODUCT_GUIDES)
        ->and(WebpageSubTypeEnum::resolveBlogCategory('tips'))->toBe(WebpageSubTypeEnum::BUSINESS_TIPS)
        ->and(WebpageSubTypeEnum::resolveBlogCategory('david_aw_news'))->toBe(WebpageSubTypeEnum::NEWSLETTERS)
        ->and(WebpageSubTypeEnum::resolveBlogCategory('blog', false))->toBeNull();
});

it('resolves the new sub types in the blog category sql expression', function () {
    $expression = WebpageSubTypeEnum::blogCategorySqlExpression();

    expect($expression)->toContain("WHEN webpages.sub_type IN ('integrations_guides') THEN 'integrations_guides'")
        ->and($expression)->toContain("WHEN webpages.sub_type IN ('dropshipping_guides') THEN 'dropshipping_guides'")
        ->and($expression)->toContain("WHEN webpages.sub_type = 'blog' THEN 'product_guides'");
});

it('gives a dropshipping shop the catch all sub type a category to be persisted as', function () {
    expect(WebpageSubTypeEnum::resolveBlogCategory('blog', false, ShopTypeEnum::DROPSHIPPING))
        ->toBe(WebpageSubTypeEnum::PRODUCT_GUIDES)
        ->and(WebpageSubTypeEnum::resolveBlogCategory('blog', true, ShopTypeEnum::DROPSHIPPING))
        ->toBe(WebpageSubTypeEnum::PRODUCT_GUIDES);
});

it('leaves the catch all sub type undecided for every other shop type', function (?ShopTypeEnum $shopType) {
    expect(WebpageSubTypeEnum::resolveBlogCategory('blog', false, $shopType))->toBeNull()
        ->and(WebpageSubTypeEnum::resolveBlogCategory('blog', true, $shopType))
        ->toBe(WebpageSubTypeEnum::PRODUCT_GUIDES);
})->with([
    null,
    ShopTypeEnum::B2B,
    ShopTypeEnum::B2C,
    ShopTypeEnum::FULFILMENT,
    ShopTypeEnum::EXTERNAL,
]);
