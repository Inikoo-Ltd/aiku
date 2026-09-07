<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Maintenance\Catalogue\RepairEscapedDescriptions;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Helpers\Language;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->shop->update(['language_id' => Language::where('code', 'en')->firstOrFail()->id]);
});

test('a product with no usable master takes its english source from the lowest same code sibling', function () {
    [, $product] = createProduct($this->shop);

    $siblings = collect(['<p>The clean english source</p>', '<p>A later duplicate</p>'])
        ->map(function (string $description, int $index) use ($product) {
            $sibling                    = $product->replicate();
            $sibling->slug              = $product->slug.'-sibling-'.$index;
            $sibling->source_id         = null;
            $sibling->master_product_id = null;
            $sibling->description       = $description;
            $sibling->save();

            return $sibling;
        });

    $product->update([
        'master_product_id' => null,
        'description'       => '<p>Kaars \<br> handgemaakt<\/p>',
    ]);

    $action  = RepairEscapedDescriptions::make();
    $changes = $action->handle($product->fresh(), apply: false, retranslate: true);

    expect($action->skipped)->toBeEmpty()
        ->and($action->failed)->toBeEmpty()
        ->and($action->retranslated)->toBe(['Product '.$product->id.' description'])
        ->and($changes)->toBeEmpty();

    /* The damage is ambiguous, so without --retranslate the same row stays untouched and is reported. */
    $reported = RepairEscapedDescriptions::make();
    $reported->handle($product->fresh(), apply: false);

    expect($reported->skipped)->toBe(['Product '.$product->id.' description'])
        ->and($reported->retranslated)->toBeEmpty();

    expect($action->englishSource($product->fresh(), 'description'))->toBe($siblings->first()->description);
});

test('a product that has a master is never given a sibling shop copy', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'ESCFIX',
        'name' => 'Escaped Descriptions Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'ESCFIX-DEP',
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'ESCFIX-FAM',
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'ESCFIX-ASSET',
        'name'    => 'escaped descriptions asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 100,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly(['description' => '']);

    [, $product] = createProduct($this->shop);

    $sibling              = $product->replicate();
    $sibling->slug        = $product->slug.'-master-sibling';
    $sibling->source_id   = null;
    $sibling->description = '<p>A sibling copy nobody asked for</p>';
    $sibling->save();

    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'description'       => '<p>Kaars \<br> handgemaakt<\/p>',
    ]);

    $action = RepairEscapedDescriptions::make();
    $action->handle($product->fresh(), apply: false, retranslate: true);

    expect($action->englishSource($product->fresh(), 'description'))->toBeNull()
        ->and($action->retranslated)->toBeEmpty()
        ->and($action->skipped)->toBe(['Product '.$product->id.' description']);
});
