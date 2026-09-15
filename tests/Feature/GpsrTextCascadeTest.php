<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-16h-22m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\TranslateProductGpsrText;
use App\Actions\Catalogue\Product\UI\EditProduct;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\EditMasterProduct;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockProductLabelInfo;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'GP'.substr(uniqid(), -6),
        'name' => 'GPSR Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'GPD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'GPF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly([
        'master_shop_id' => $masterShop->id,
        'language_id'    => Language::where('code', 'en')->first()->id,
    ]);

    $this->bottle = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());

    $this->masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'        => 'GP-AST'.substr(uniqid(), -6),
        'name'        => 'gpsr asset',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->bottle->id, 'quantity' => 1],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'GPP'.substr(uniqid(), -8),
            'price' => 10,
            'unit'  => 'piece',
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
    $this->product->tradeUnits()->sync([$this->bottle->id => ['quantity' => 1]]);
});

function useShopLanguage($shop, string $code): void
{
    $shop->updateQuietly(['language_id' => Language::where('code', $code)->first()->id]);
}

test('trade unit GPSR texts are copied to a shop that speaks English and recorded on the master', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'gpsr_warnings' => 'Keep away from children',
        'gpsr_manual'   => 'Apply twice a day',
    ]);

    $product     = $this->product->refresh();
    $masterAsset = $this->masterAsset->refresh();

    expect($product->gpsr_warnings)->toBe('Keep away from children')
        ->and($product->gpsr_manual)->toBe('Apply twice a day')
        ->and($product->getTranslation('gpsr_warnings_i8n', 'en'))->toBe('Keep away from children')
        ->and($masterAsset->gpsr_manual)->toBe('Apply twice a day')
        ->and($masterAsset->getTranslation('gpsr_warnings_i8n', 'en'))->toBe('Keep away from children')
        ->and($masterAsset->getTranslation('gpsr_manual_i8n', 'en'))->toBe('Apply twice a day');
});

test('trade unit GPSR text is machine translated for a shop in another language', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('Tenir hors de portée des enfants');
    useShopLanguage($this->shop, 'fr');

    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_warnings' => 'Keep away from children']);

    $product = $this->product->refresh();

    expect($product->gpsr_warnings)->toBe('Tenir hors de portée des enfants')
        ->and($product->getTranslation('gpsr_warnings_i8n', 'en'))->toBe('Keep away from children')
        ->and($product->getTranslation('gpsr_warnings_i8n', 'fr'))->toBe('Tenir hors de portée des enfants')
        ->and($product->is_gpsr_warnings_reviewed)->toBeNull()
        ->and($this->masterAsset->refresh()->getTranslation('gpsr_warnings_i8n', 'fr'))->toBe('Tenir hors de portée des enfants');
});

test('the GPSR translation is queued instead of run during the trade unit update', function () {
    Queue::fake();
    Translate::mock()->shouldReceive('handle')->never();
    useShopLanguage($this->shop, 'fr');

    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_warnings' => 'Keep away from children']);

    $product = $this->product->refresh();

    expect($product->getTranslation('gpsr_warnings_i8n', 'en'))->toBe('Keep away from children')
        ->and($product->gpsr_warnings)->not->toBe('Keep away from children');

    TranslateProductGpsrText::assertPushed(fn ($action, $arguments) => $arguments[0]->id == $product->id && $arguments[1] == ['gpsr_warnings']);
});

test('an unrelated trade unit change does not translate the GPSR text again', function () {
    Translate::mock()->shouldReceive('handle')->once()->andReturn('Tenir hors de portée des enfants');
    useShopLanguage($this->shop, 'fr');

    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_warnings' => 'Keep away from children']);
    UpdateTradeUnit::make()->action($this->bottle, ['un_number' => 'UN1263']);

    expect($this->product->refresh()->gpsr_warnings)->toBe('Tenir hors de portée des enfants');
});

test('a GPSR text written by the shop is kept and flagged for review when the trade unit changes', function () {
    Translate::mock()->shouldReceive('handle')->never();
    useShopLanguage($this->shop, 'sk');

    patch(route('grp.models.product.update', $this->product->id), ['gpsr_manual' => 'Aplikujte dvakrát denne'])
        ->assertRedirect();

    expect($this->product->refresh()->is_gpsr_manual_reviewed)->toBeTrue()
        ->and($this->product->getTranslation('gpsr_manual_i8n', 'sk'))->toBe('Aplikujte dvakrát denne')
        ->and($this->masterAsset->refresh()->getTranslation('gpsr_manual_i8n', 'sk'))->toBe('Aplikujte dvakrát denne');

    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_manual' => 'Apply twice a day']);
    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_manual' => 'Apply three times a day']);

    $product = $this->product->refresh();

    expect($product->gpsr_manual)->toBe('Aplikujte dvakrát denne')
        ->and($product->is_gpsr_manual_reviewed)->toBeFalse()
        ->and($product->getTranslation('gpsr_manual_i8n', 'en'))->toBe('Apply three times a day');
});

test('a GPSR text edited on the master reaches its products translated', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('Appliquer deux fois par jour');
    useShopLanguage($this->shop, 'fr');

    UpdateMasterAsset::make()->action($this->masterAsset, ['gpsr_manual' => 'Apply twice a day']);

    $product = $this->product->refresh();

    expect($this->masterAsset->refresh()->getTranslation('gpsr_manual_i8n', 'en'))->toBe('Apply twice a day')
        ->and($product->gpsr_manual)->toBe('Appliquer deux fois par jour')
        ->and($product->getTranslation('gpsr_manual_i8n', 'en'))->toBe('Apply twice a day');
});

test('the product label info shows the GPSR texts in the shop language', function () {
    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (string $text) => 'FR: '.$text);
    useShopLanguage($this->shop, 'fr');

    UpdateTradeUnit::make()->action($this->bottle, [
        'gpsr_warnings' => 'Keep away from children',
        'gpsr_manual'   => 'Apply twice a day',
    ]);

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product->refresh());

    expect($labelInfo['direction_for_use'])->toBe(['show' => true, 'label' => 'Direction For Use', 'value' => 'FR: Apply twice a day'])
        ->and($labelInfo['warnings_and_precautions'])->toBe(['show' => true, 'label' => 'Warning & Precautions', 'value' => 'FR: Keep away from children']);
});

test('the GPSR texts can be edited on the product and on the master product', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['gpsr_warnings' => 'Keep away from children']);

    $productFields = collect(EditProduct::make()->getBlueprint($this->product->refresh()))->pluck('fields')->collapse();
    $masterFields  = collect(EditMasterProduct::make()->getBlueprint($this->masterAsset->refresh()))->pluck('fields')->collapse();

    expect($productFields['gpsr_warnings'])->toMatchArray([
        'type'  => 'input_translation',
        'main'  => 'Keep away from children',
        'value' => 'Keep away from children',
    ])
        ->and($productFields)->toHaveKey('gpsr_manual')
        ->and($masterFields['gpsr_warnings'])->toMatchArray(['type' => 'input', 'value' => 'Keep away from children'])
        ->and($masterFields)->toHaveKey('gpsr_manual');
});
