<?php

use App\Actions\Maintenance\Web\RepairDropshippingLegacyBlogSubType;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

beforeEach(function () {
    loadDB();
    $this->shop = createShop()[2];
    $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
    $this->website = createWebsite($this->shop);
});

function createDropshippingBlogWebpage(Website $website, string $subType): Webpage
{
    $webpage = StoreWebpage::make()->action($website, array_merge(
        Webpage::factory()->definition(),
        [
            'type'     => WebpageTypeEnum::BLOG->value,
            'sub_type' => WebpageSubTypeEnum::BLOG->value,
        ]
    ));

    $webpage->updateQuietly(['sub_type' => $subType]);

    return $webpage->refresh();
}

test('dropshipping blogs with a legacy sub type are moved to dropshipping guides', function () {
    $legacyBlog   = createDropshippingBlogWebpage($this->website, WebpageSubTypeEnum::BLOG->value);
    $businessTips = createDropshippingBlogWebpage($this->website, WebpageSubTypeEnum::BUSINESS_TIPS->value);
    $productGuide = createDropshippingBlogWebpage($this->website, WebpageSubTypeEnum::PRODUCT_GUIDES->value);
    $mailshot     = createDropshippingBlogWebpage($this->website, WebpageSubTypeEnum::MAILSHOT->value);

    $action = RepairDropshippingLegacyBlogSubType::make();

    expect($action->getWebpagesToRepair()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$legacyBlog->id, $businessTips->id])->sort()->values()->all());

    foreach ($action->getWebpagesToRepair() as $webpage) {
        $action->handle($webpage);
    }

    expect($legacyBlog->refresh()->sub_type)->toBe(WebpageSubTypeEnum::DROPSHIPPING_GUIDES)
        ->and($legacyBlog->canonical_url)->toContain('/dropshipping-guides/')
        ->and($businessTips->refresh()->sub_type)->toBe(WebpageSubTypeEnum::DROPSHIPPING_GUIDES)
        ->and($productGuide->refresh()->sub_type)->toBe(WebpageSubTypeEnum::PRODUCT_GUIDES)
        ->and($mailshot->refresh()->sub_type)->toBe(WebpageSubTypeEnum::MAILSHOT)
        ->and($action->getWebpagesToRepair())->toBeEmpty();
});

test('a dry run leaves the sub type untouched', function () {
    $businessTips = createDropshippingBlogWebpage($this->website, WebpageSubTypeEnum::BUSINESS_TIPS->value);

    RepairDropshippingLegacyBlogSubType::make()->handle($businessTips, dryRun: true);

    expect($businessTips->refresh()->sub_type)->toBe(WebpageSubTypeEnum::BUSINESS_TIPS);
});
