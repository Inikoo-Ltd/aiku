<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockProductLabelInfo;
use App\Models\Helpers\Country;
use App\Models\Helpers\Tag;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateBulkTradeUnitGpsr;
use App\Actions\Goods\TradeUnit\UpdateBulkTradeUnitLabelInfo;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Arr;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\StoreMasterProductFromTradeUnits;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
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
    actingAs($this->adminGuest->getUser());

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'LI'.substr(uniqid(), -6),
        'name' => 'Label Info Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'LID-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'LIF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->bottle = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());
    $this->plug   = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());

    $this->masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'        => 'LI-AST'.substr(uniqid(), -6),
        'name'        => 'label info asset',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->bottle->id, 'quantity' => 1],
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'LIP'.substr(uniqid(), -8),
            'price' => 10,
            'unit'  => 'piece',
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
    $this->product->tradeUnits()->sync([
        $this->bottle->id => ['quantity' => 1],
        $this->plug->id   => ['quantity' => 1],
    ]);
});

test('label presence flags are saved into the trade unit label info', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'ce_marking' => true,
        'ip_rating'  => false,
    ]);

    expect($this->bottle->refresh()->label_info)->toMatchArray([
        'ce_marking' => true,
        'ip_rating'  => false,
    ]);
});

test('a master asset shows a flag when any of its trade units has it', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'ce_marking'   => true,
        'ukca_marking' => false,
    ]);

    UpdateTradeUnit::make()->action($this->plug, ['ce_marking' => true]);
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => false]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeTrue();

    UpdateTradeUnit::make()->action($this->plug, ['ce_marking' => false]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeFalse();
});

test('a product following its master copies the master label info', function () {
    $this->masterAsset->updateQuietly(['label_info' => ['weee_symbol' => true]]);

    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true]);

    expect($this->product->refresh()->label_info)->toMatchArray($this->masterAsset->refresh()->label_info)
        ->and($this->product->label_info['ce_marking'])->toBeTrue();
});

test('a product not following its master takes label info from its own trade units', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    $this->product->tradeUnits()->sync([$this->plug->id => ['quantity' => 1]]);

    UpdateTradeUnit::make()->action($this->bottle, ['ukca_marking' => true]);

    expect($this->masterAsset->refresh()->label_info['ukca_marking'])->toBeTrue()
        ->and(data_get($this->product->refresh()->label_info, 'ukca_marking', false))->toBeFalse();

    UpdateTradeUnit::make()->action($this->plug, ['ip_rating' => true]);

    expect($this->product->refresh()->label_info['ip_rating'])->toBeTrue();
});

test('label presence is exposed with show only when the stored value is true', function () {
    expect(TradeUnitLabelPresenceEnum::presenceFromLabelInfo([
        'ce_marking'   => true,
        'ukca_marking' => 'true',
        'weee_symbol'  => false,
    ]))->toBe([
        'ce_marking'                    => ['show' => true],
        'ukca_marking'                  => ['show' => false],
        'weee_symbol'                   => ['show' => false],
        'ip_rating'                     => ['show' => false],
        'sorting_recycling_information' => ['show' => false],
        'safety_icons'                  => ['show' => false],
        'batch_number'                  => ['show' => false],
    ])
        ->and(TradeUnitLabelPresenceEnum::presenceFromLabelInfo(null)['ce_marking'])->toBe(['show' => false]);
});

test('trade unit, product and master product showcases include label presence', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['weee_symbol' => true]);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->product->shop->slug,
        $this->product->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.weee_symbol.show', true)
        ->where('showcase.label_info.ce_marking.show', false)
        ->etc());

    get(route('grp.trade_units.units.show', [$this->bottle->refresh()->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.weee_symbol.show', true)
            ->where('showcase.label_info.ce_marking.show', false)
            ->etc());

    get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterAsset->masterShop->slug,
        $this->masterAsset->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.weee_symbol.show', true)
        ->where('showcase.label_info.ce_marking.show', false)
        ->etc());
});

test('markets and languages are saved into the trade unit label info and shown on its showcase', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'markets'   => [
            ['label' => 'UK', 'key' => 'uk', 'value' => false],
            ['label' => 'EU', 'key' => 'eu', 'value' => true],
            ['label' => 'Other International Markets', 'key' => 'other', 'value' => 'true'],
        ],
        'languages' => ['en', 'fr'],
    ]);

    expect($this->bottle->refresh()->label_info)->toMatchArray([
        'markets'   => ['eu', 'other'],
        'languages' => ['en', 'fr'],
    ]);

    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.markets.show', true)
            ->where('showcase.label_info.markets.value', [['value' => 'eu', 'label' => 'EU'], ['value' => 'other', 'label' => 'Other International Markets']])
            ->where('showcase.label_info.languages.show', true)
            ->where('showcase.label_info.languages.value', fn ($languages) => collect($languages)->pluck('code')->sort()->values()->all() === ['en', 'fr'])
            ->etc());
});

test('an unknown market is rejected', function (string $market) {
    UpdateTradeUnit::make()->action($this->bottle, ['markets' => [$market]]);
})->with(['us', 'es'])->throws(Illuminate\Validation\ValidationException::class);

test('the market checkboxes offer uk, eu and other international markets', function () {
    expect(TradeUnitMarketEnum::checkboxValue(['other']))->toBe([
        ['label' => 'UK', 'key' => 'uk', 'value' => false],
        ['label' => 'EU', 'key' => 'eu', 'value' => false],
        ['label' => 'Other International Markets', 'key' => 'other', 'value' => true],
    ]);
});

test('a master product created from trade units starts with their label presence', function () {
    UpdateTradeUnit::make()->action($this->plug, ['ip_rating' => true]);

    $masterAsset = StoreMasterProductFromTradeUnits::make()->action($this->masterFamily, [
        'code'              => 'LIN'.substr(uniqid(), -6),
        'name'              => 'label info new master',
        'unit'              => 'piece',
        'master_prices'     => [],
        'master_rrps'       => [],
        'is_minion_variant' => false,
        'is_for_sale'       => true,
        'trade_units'       => [
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    expect($masterAsset->refresh()->label_info)->toMatchArray([
        'ip_rating'  => true,
        'ce_marking' => false,
    ]);
});

test('a master keeps only the markets every trade unit shares and unions their languages', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['markets' => ['uk', 'eu'], 'languages' => ['fr', 'en']]);
    UpdateTradeUnit::make()->action($this->plug, ['markets' => ['eu', 'other', 'uk'], 'languages' => ['en', 'es']]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'markets'   => ['uk', 'eu'],
        'languages' => ['en', 'es', 'fr'],
    ])
        ->and($this->product->refresh()->label_info)->toMatchArray([
            'markets'   => ['uk', 'eu'],
            'languages' => ['en', 'es', 'fr'],
        ]);

    UpdateTradeUnit::make()->action($this->plug, ['markets' => ['other']]);

    expect($this->masterAsset->refresh()->label_info['markets'])->toBe([])
        ->and($this->product->refresh()->label_info['markets'])->toBe([]);
});

test('changing a master composition recalculates its label info and its following products', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['ce_marking' => true, 'markets' => ['uk']]);

    expect($this->masterAsset->refresh()->label_info['ce_marking'])->toBeTrue();

    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->plug->id, 'quantity' => 1],
        ],
    ]);

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'ce_marking' => false,
        'markets'    => [],
    ])
        ->and($this->product->refresh()->label_info['ce_marking'])->toBeFalse();
});

test('changing an independent product composition recalculates its label info', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    UpdateTradeUnit::make()->action($this->bottle, ['weee_symbol' => true, 'languages' => ['de']]);

    SyncProductTradeUnits::run($this->product->refresh(), [
        ['id' => $this->plug->id, 'quantity' => 1],
    ]);

    expect($this->product->refresh()->label_info)->toMatchArray([
        'weee_symbol' => false,
        'languages'   => [],
    ]);

    SyncProductTradeUnits::run($this->product->refresh(), [
        ['id' => $this->bottle->id, 'quantity' => 1],
    ]);

    expect($this->product->refresh()->label_info)->toMatchArray([
        'weee_symbol' => true,
        'languages'   => ['de'],
    ]);
});

test('batch number flows down from any trade unit to master products and products', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    $this->product->tradeUnits()->sync([$this->bottle->id => ['quantity' => 1]]);

    UpdateTradeUnit::make()->action($this->bottle, ['batch_number' => true]);

    expect($this->masterAsset->refresh()->label_info['batch_number'])->toBeTrue()
        ->and($this->product->refresh()->label_info['batch_number'])->toBeTrue();

    UpdateTradeUnit::make()->action($this->plug, ['batch_number' => true]);
    UpdateTradeUnit::make()->action($this->bottle, ['batch_number' => false]);

    expect($this->masterAsset->refresh()->label_info['batch_number'])->toBeTrue()
        ->and($this->product->refresh()->label_info['batch_number'])->toBeFalse();
});

test('the product web block label info carries the product data in label, show and value items', function () {
    $country = Country::where('code', 'GB')->first();

    UpdateTradeUnit::make()->action($this->bottle, ['markets' => ['uk'], 'ip_rating' => true]);
    UpdateTradeUnit::make()->action($this->plug, ['markets' => ['uk', 'eu']]);

    $this->product->refresh()->updateQuietly([
        'origin_country_id' => $country->id,
        'gpsr_manufacturer' => 'Ancient Wisdom',
        'gpsr_manual'       => 'Apply twice a day',
        'gpsr_warnings'     => '',
        'ufi_number'            => 'UFI-1234',
        'marketing_weight'      => 1250,
        'barcode'               => '5055796512345',
        'marketing_ingredients' => 'Aqua, Glycerin',
        'pictogram_toxic'       => true,
        'pictogram_flammable'   => true,
        'label_info'            => array_merge($this->product->label_info, ['batch_number' => true]),
    ]);

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product->refresh());

    expect(array_keys($labelInfo))->toBe([
        'markets', 'batch_number', 'country_of_origin', 'barcode', 'manufacturer', 'uk_responsible_person',
        'eu_responsible_person', 'languages', 'best_before', 'ingredients', 'direction_for_use', 'warnings_and_precautions',
        'clp_ghs_pictograms', 'ufi_number', 'safety_icons', 'net_quantity', 'packaging_material_codes',
        'ce_marking', 'ukca_marking', 'weee_symbol', 'ip_rating', 'sorting_recycling_information',
    ])
        ->and(collect($labelInfo)->except(['ce_marking', 'weee_symbol'])->every(fn ($item) => array_keys($item) === ['show', 'label', 'value']))->toBeTrue()
        ->and(array_keys($labelInfo['ce_marking']))->toBe(['show', 'label', 'value', 'mark'])
        ->and(array_keys($labelInfo['weee_symbol']))->toBe(['show', 'label', 'value', 'mark'])
        ->and($labelInfo['ip_rating'])->toBe(['show' => true, 'label' => 'IP Rating', 'value' => true])
        ->and($labelInfo['batch_number'])->toBe(['show' => true, 'label' => 'Batch Number', 'value' => true])
        ->and($labelInfo['markets'])->toBe(['show' => true, 'label' => 'Markets', 'value' => [['value' => 'uk', 'label' => 'UK']]])
        ->and($labelInfo['country_of_origin'])->toBe(['show' => true, 'label' => 'Country Of Origin', 'value' => ['code' => 'GB', 'name' => $country->name]])
        ->and($labelInfo['barcode'])->toBe(['show' => true, 'label' => 'Barcode / EAN', 'value' => '5055796512345'])
        ->and($labelInfo['manufacturer'])->toBe(['show' => true, 'label' => 'Manufacturer Details', 'value' => 'Ancient Wisdom'])
        ->and($labelInfo['ingredients'])->toBe(['show' => true, 'label' => 'Ingredients', 'value' => 'Aqua, Glycerin'])
        ->and($labelInfo['direction_for_use'])->toBe(['show' => true, 'label' => 'Direction For Use', 'value' => 'Apply twice a day'])
        ->and($labelInfo['warnings_and_precautions'])->toBe(['show' => false, 'label' => 'Warning & Precautions', 'value' => null])
        ->and($labelInfo['ufi_number'])->toBe(['show' => true, 'label' => 'UFI Number', 'value' => 'UFI-1234'])
        ->and($labelInfo['net_quantity'])->toBe(['show' => true, 'label' => 'Net Quantity', 'value' => ['grams' => 1250.0, 'formatted' => '1.25 kg']])
        ->and($labelInfo['uk_responsible_person']['show'])->toBeTrue()
        ->and($labelInfo['eu_responsible_person']['show'])->toBeFalse()
        ->and(collect($labelInfo['clp_ghs_pictograms']['value'])->pluck('key')->all())->toBe(['toxic', 'flammable']);
});

test('net quantity is hidden from the product page when a trade unit turns it off', function () {
    $this->product->updateQuietly(['marketing_weight' => 500]);

    $labelInfoBuilder = new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    };

    expect($labelInfoBuilder->build($this->product->refresh())['net_quantity']['show'])->toBeTrue();

    UpdateTradeUnit::make()->action($this->bottle, ['show_net_quantity' => false]);

    expect($this->bottle->refresh()->label_info['show_net_quantity'])->toBeFalse()
        ->and($this->masterAsset->refresh()->label_info['show_net_quantity'])->toBeFalse()
        ->and($this->product->refresh()->label_info['show_net_quantity'])->toBeFalse()
        ->and($labelInfoBuilder->build($this->product)['net_quantity'])->toBe(['show' => false, 'label' => 'Net Quantity', 'value' => null]);

    UpdateTradeUnit::make()->action($this->bottle, ['show_net_quantity' => true]);

    expect($this->product->refresh()->label_info['show_net_quantity'])->toBeTrue()
        ->and($labelInfoBuilder->build($this->product)['net_quantity']['show'])->toBeTrue();
});

test('packaging material codes and their visibility are saved into the trade unit label info', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'packaging_material_codes'      => ['pap_20', 'gl_70'],
        'packaging_material_codes_show' => true,
    ]);

    expect($this->bottle->refresh()->label_info['packaging_material_codes'])->toBe([
        'show'  => true,
        'value' => ['pap_20', 'gl_70'],
    ]);

    UpdateTradeUnit::make()->action($this->bottle, ['packaging_material_codes_show' => false]);

    expect($this->bottle->refresh()->label_info['packaging_material_codes'])->toBe([
        'show'  => false,
        'value' => ['pap_20', 'gl_70'],
    ]);
});

test('an unknown packaging material code is rejected', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['packaging_material_codes' => ['pap_99']]);
})->throws(Illuminate\Validation\ValidationException::class);

test('packaging material codes flow down from the trade units that show them', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'packaging_material_codes'      => ['gl_70', 'pap_20'],
        'packaging_material_codes_show' => true,
    ]);
    UpdateTradeUnit::make()->action($this->plug, [
        'packaging_material_codes'      => ['fe_40'],
        'packaging_material_codes_show' => false,
    ]);

    expect($this->masterAsset->refresh()->label_info['packaging_material_codes'])->toBe([
        'show'  => true,
        'value' => ['pap_20', 'gl_70'],
    ])
        ->and($this->product->refresh()->label_info['packaging_material_codes'])->toBe([
            'show'  => true,
            'value' => ['pap_20', 'gl_70'],
        ]);

    UpdateTradeUnit::make()->action($this->plug, ['packaging_material_codes_show' => true]);

    expect($this->masterAsset->refresh()->label_info['packaging_material_codes']['value'])->toBe(['pap_20', 'fe_40', 'gl_70']);

    get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterAsset->masterShop->slug,
        $this->masterAsset->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.packaging_material_codes.show', true)
        ->where('showcase.label_info.packaging_material_codes.value.0', ['value' => 'pap_20', 'code' => 'PAP 20', 'material' => 'Corrugated cardboard'])
        ->etc());

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product->refresh());

    expect(collect($labelInfo['packaging_material_codes']['value'])->pluck('code')->all())->toBe(['PAP 20', 'FE 40', 'GL 70']);

    UpdateTradeUnit::make()->action($this->bottle, ['packaging_material_codes_show' => false]);
    UpdateTradeUnit::make()->action($this->plug, ['packaging_material_codes_show' => false]);

    expect($this->product->refresh()->label_info['packaging_material_codes'])->toBe(['show' => false, 'value' => []]);
});

test('safety icons flow down from any trade unit and only show on the showcase when present', function () {
    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.safety_icons.show', false)
            ->etc());

    UpdateTradeUnit::make()->action($this->plug, ['safety_icons' => true]);

    expect($this->masterAsset->refresh()->label_info['safety_icons'])->toBeTrue()
        ->and($this->product->refresh()->label_info['safety_icons'])->toBeTrue();

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->product->shop->slug,
        $this->product->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.safety_icons.show', true)
        ->etc());

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product);

    expect($labelInfo['safety_icons'])->toBe(['show' => true, 'label' => 'Safety Icons', 'value' => true]);

    UpdateTradeUnit::make()->action($this->plug, ['safety_icons' => false]);

    expect($this->product->refresh()->label_info['safety_icons'])->toBeFalse();
});

test('best before is saved on the trade unit and flows down only when every trade unit shares it', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['best_before' => 'pao_12m']);

    expect($this->bottle->refresh()->label_info['best_before'])->toBe('pao_12m')
        ->and($this->masterAsset->refresh()->label_info['best_before'])->toBeNull();

    UpdateTradeUnit::make()->action($this->plug, ['best_before' => 'pao_12m']);

    expect($this->masterAsset->refresh()->label_info['best_before'])->toBe('pao_12m')
        ->and($this->product->refresh()->label_info['best_before'])->toBe('pao_12m');

    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.best_before', ['show' => true, 'value' => ['value' => 'pao_12m', 'label' => 'PAO 12M']])
            ->etc());

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product);

    expect($labelInfo['best_before'])->toBe([
        'show'  => true,
        'label' => 'PAO / Expiry Date / Best Before',
        'value' => ['value' => 'pao_12m', 'label' => 'PAO 12M'],
    ]);

    UpdateTradeUnit::make()->action($this->plug, ['best_before' => 'custom']);

    expect($this->product->refresh()->label_info['best_before'])->toBeNull();

    UpdateTradeUnit::make()->action($this->bottle, ['best_before' => null]);

    expect($this->bottle->refresh()->label_info['best_before'])->toBeNull();
});

test('no expiry date stays on the showcase but is hidden from the product web block', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['best_before' => 'no_expiry_date']);
    UpdateTradeUnit::make()->action($this->plug, ['best_before' => 'no_expiry_date']);

    expect($this->product->refresh()->label_info['best_before'])->toBe('no_expiry_date');

    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.best_before', ['show' => true, 'value' => ['value' => 'no_expiry_date', 'label' => 'No Expiry Date']])
            ->etc());

    $labelInfo = (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): array
        {
            return $this->getProductLabelInfo($product);
        }
    })->build($this->product);

    expect($labelInfo['best_before'])->toBe([
        'show'  => false,
        'label' => 'PAO / Expiry Date / Best Before',
        'value' => null,
    ]);
});

test('an unknown best before option is rejected', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['best_before' => 'pao_36m']);
})->throws(Illuminate\Validation\ValidationException::class);

test('the label info approval is saved on the trade unit and shown on its showcase', function () {
    expect(data_get($this->bottle->label_info, 'label_info_approved', false))->toBeFalse();

    UpdateTradeUnit::make()->action($this->bottle, ['label_info_approved' => true]);

    expect($this->bottle->refresh()->label_info['label_info_approved'])->toBeTrue();

    get(route('grp.trade_units.units.show', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.label_info.label_info_approved.show', true)
            ->etc());
});

test('the regulatory tab is only published when every trade unit of the product is approved', function () {
    UpdateTradeUnit::make()->action($this->bottle, ['label_info_approved' => true]);

    expect($this->masterAsset->refresh()->label_info['label_info_approved'])->toBeFalse()
        ->and($this->product->refresh()->label_info['label_info_approved'])->toBeFalse();

    UpdateTradeUnit::make()->action($this->plug, ['label_info_approved' => true]);

    expect($this->masterAsset->refresh()->label_info['label_info_approved'])->toBeTrue()
        ->and($this->product->refresh()->label_info['label_info_approved'])->toBeTrue();

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->product->shop->slug,
        $this->product->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('showcase.label_info.label_info_approved.show', true)
        ->etc());

    UpdateTradeUnit::make()->action($this->plug, ['label_info_approved' => false]);

    expect($this->masterAsset->refresh()->label_info['label_info_approved'])->toBeFalse()
        ->and($this->product->refresh()->label_info['label_info_approved'])->toBeFalse();
});

test('an independent product takes the approval from its own trade units', function () {
    $this->product->updateQuietly(['not_follow_master_trade_units' => true]);
    $this->product->tradeUnits()->sync([$this->plug->id => ['quantity' => 1]]);

    UpdateTradeUnit::make()->action($this->plug, ['label_info_approved' => true]);

    expect($this->product->refresh()->label_info['label_info_approved'])->toBeTrue()
        ->and($this->masterAsset->refresh()->label_info['label_info_approved'])->toBeFalse();
});

test('the product web block tells the website whether the label info is approved', function () {
    $buildApproval = fn ($product) => (new class () {
        use HasWebBlockProductLabelInfo;

        public function build($product): bool
        {
            return $this->isProductLabelInfoApproved($product);
        }
    })->build($product);

    expect($buildApproval($this->product->refresh()))->toBeFalse();

    UpdateTradeUnit::make()->action($this->bottle, ['label_info_approved' => true]);
    UpdateTradeUnit::make()->action($this->plug, ['label_info_approved' => true]);

    expect($buildApproval($this->product->refresh()))->toBeTrue();
});

dataset('bulkLabelInfo', [
    fn () => [
        'label_info_approved'           => true,
        'show_net_quantity'             => false,
        'markets'                       => ['uk', 'eu'],
        'languages'                     => ['en'],
        'best_before'                   => 'pao_12m',
        'packaging_material_codes'      => ['pap_20'],
        'packaging_material_codes_show' => true,
        'batch_number'                  => true,
        'ce_marking'                    => true,
        'ukca_marking'                  => false,
        'weee_symbol'                   => false,
        'ip_rating'                     => true,
        'sorting_recycling_information' => false,
        'safety_icons'                  => false,
    ],
]);

test('bulk editing overrides the label info of every selected trade unit', function (array $bulkLabelInfo) {
    UpdateTradeUnit::make()->action($this->bottle, [
        'weee_symbol' => true,
        'markets'     => ['other'],
        'languages'   => ['fr', 'de'],
    ]);

    UpdateBulkTradeUnitLabelInfo::make()->action(group(), [
        ...$bulkLabelInfo,
        'trade_units' => [$this->bottle->id, $this->plug->id],
    ]);

    foreach ([$this->bottle, $this->plug] as $tradeUnit) {
        expect($tradeUnit->refresh()->label_info)->toMatchArray([
            'label_info_approved'      => true,
            'show_net_quantity'        => false,
            'markets'                  => ['uk', 'eu'],
            'languages'                => ['en'],
            'best_before'              => 'pao_12m',
            'packaging_material_codes' => ['show' => true, 'value' => ['pap_20']],
            'ce_marking'               => true,
            'weee_symbol'              => false,
            'ip_rating'                => true,
        ]);
    }

    expect($this->masterAsset->refresh()->label_info)->toMatchArray([
        'label_info_approved' => true,
        'markets'             => ['uk', 'eu'],
        'weee_symbol'         => false,
    ]);
})->with('bulkLabelInfo');

test('bulk editing through the route only touches the selected trade units', function (array $bulkLabelInfo) {
    $this->patch(route('grp.models.trade_units.bulk_update_label_info'), [
        ...$bulkLabelInfo,
        'markets'     => ['eu'],
        'trade_units' => [$this->bottle->id],
    ])->assertSessionHasNoErrors();

    expect($this->bottle->refresh()->label_info['markets'])->toBe(['eu'])
        ->and(data_get($this->plug->refresh()->label_info, 'markets'))->toBeNull();
})->with('bulkLabelInfo');

test('bulk editing a single label info field leaves the rest of the label info as it was', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'ce_marking'          => true,
        'languages'           => ['fr', 'de'],
        'label_info_approved' => true,
    ]);

    $this->patch(route('grp.models.trade_units.bulk_update_label_info'), [
        'markets'     => ['uk'],
        'trade_units' => [$this->bottle->id, $this->plug->id],
    ])->assertSessionHasNoErrors();

    expect($this->bottle->refresh()->label_info)->toMatchArray([
        'markets'             => ['uk'],
        'ce_marking'          => true,
        'languages'           => ['fr', 'de'],
        'label_info_approved' => true,
    ])
        ->and($this->plug->refresh()->label_info['markets'])->toBe(['uk'])
        ->and(data_get($this->plug->label_info, 'ce_marking'))->toBeNull();
});

test('bulk editing packaging material codes on their own also sends their visibility', function () {
    UpdateBulkTradeUnitLabelInfo::make()->action(group(), [
        'packaging_material_codes'      => ['pap_20'],
        'packaging_material_codes_show' => true,
        'trade_units'                   => [$this->bottle->id],
    ]);

    expect($this->bottle->refresh()->label_info['packaging_material_codes'])->toBe(['show' => true, 'value' => ['pap_20']]);
});

test('bulk editing label info refuses a save with no field in it', function () {
    UpdateBulkTradeUnitLabelInfo::make()->action(group(), [
        'trade_units' => [$this->bottle->id],
    ]);
})->throws(Illuminate\Validation\ValidationException::class);

test('bulk editing rejects an empty selection', function (array $bulkLabelInfo) {
    UpdateBulkTradeUnitLabelInfo::make()->action(group(), [
        ...$bulkLabelInfo,
        'trade_units' => [],
    ]);
})->with('bulkLabelInfo')->throws(Illuminate\Validation\ValidationException::class);

test('the trade units index offers a fresh bulk edit label info form', function () {
    get(route('grp.trade_units.units.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('is_checkbox', true)
            ->where('bulk_edit.sections.label_info.label', 'Labeling & Compliance Marks')
            ->where('bulk_edit.sections.label_info.icon', 'fa-light fa-stamp')
            ->where('bulk_edit.sections.label_info.updateRoute.name', 'grp.models.trade_units.bulk_update_label_info')
            ->where('bulk_edit.sections.label_info.fields.label_info_approved.value', false)
            ->where('bulk_edit.sections.label_info.fields.show_net_quantity.value', true)
            ->where('bulk_edit.sections.label_info.fields.languages.value', [])
            ->where('bulk_edit.sections.label_info.fields.markets.value', fn ($markets) => collect($markets)->every(fn ($market) => $market['value'] === false))
            ->where('bulk_edit.sections.gpsr.label', 'GPSR')
            ->where('bulk_edit.sections.gpsr.updateRoute.name', 'grp.models.trade_units.bulk_update_gpsr')
            ->where('bulk_edit.sections.gpsr.fields.gpsr_manufacturer.value', null)
            ->where('bulk_edit.sections.gpsr.fields.pictogram_toxic.value', false)
            ->etc());
});

dataset('bulkGpsr', [
    fn () => [
        'gpsr_manufacturer'          => 'Ancient Wisdom, Sheffield',
        'gpsr_eu_responsible'        => null,
        'gpsr_warnings'              => 'Keep out of reach of children',
        'gpsr_manual'                => null,
        'gpsr_class_category_danger' => null,
        'pictogram_toxic'            => false,
        'pictogram_corrosive'        => false,
        'pictogram_explosive'        => false,
        'pictogram_flammable'        => true,
        'pictogram_gas'              => false,
        'pictogram_environment'      => false,
        'pictogram_health'           => false,
        'pictogram_oxidising'        => false,
        'pictogram_danger'           => false,
    ],
]);

test('bulk editing gpsr overrides the gpsr of every selected trade unit', function (array $bulkGpsr) {
    UpdateTradeUnit::make()->action($this->bottle, [
        'gpsr_manual'     => 'Apply twice a day',
        'pictogram_toxic' => true,
    ]);

    $this->patch(route('grp.models.trade_units.bulk_update_gpsr'), [
        ...$bulkGpsr,
        'trade_units' => [$this->bottle->id, $this->plug->id],
    ])->assertSessionHasNoErrors();

    foreach ([$this->bottle, $this->plug] as $tradeUnit) {
        $tradeUnit->refresh();

        expect($tradeUnit->gpsr_manufacturer)->toBe('Ancient Wisdom, Sheffield')
            ->and($tradeUnit->gpsr_warnings)->toBe('Keep out of reach of children')
            ->and($tradeUnit->gpsr_manual)->toBeNull()
            ->and($tradeUnit->pictogram_toxic)->toBeFalse()
            ->and($tradeUnit->pictogram_flammable)->toBeTrue();
    }
})->with('bulkGpsr');

test('bulk editing a single gpsr field leaves the rest of the gpsr as it was', function () {
    UpdateTradeUnit::make()->action($this->bottle, [
        'gpsr_manual'     => 'Apply twice a day',
        'pictogram_toxic' => true,
    ]);

    $this->patch(route('grp.models.trade_units.bulk_update_gpsr'), [
        'gpsr_manufacturer' => 'Ancient Wisdom, Sheffield',
        'trade_units'       => [$this->bottle->id],
    ])->assertSessionHasNoErrors();

    $this->bottle->refresh();

    expect($this->bottle->gpsr_manufacturer)->toBe('Ancient Wisdom, Sheffield')
        ->and($this->bottle->gpsr_manual)->toBe('Apply twice a day')
        ->and($this->bottle->pictogram_toxic)->toBeTrue();
});

test('bulk editing gpsr refuses a save with no field in it', function () {
    UpdateBulkTradeUnitGpsr::make()->action(group(), [
        'trade_units' => [$this->bottle->id],
    ]);
})->throws(Illuminate\Validation\ValidationException::class);

test('bulk editing is hidden and refused for users who can only view goods', function (array $bulkLabelInfo, array $bulkGpsr) {
    setPermissionsTeamId($this->group->id);
    $viewer = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $viewer->givePermissionTo('goods.view');
    actingAs($viewer);

    $tradeUnitFamily = StoreTradeUnitFamily::make()->action(group(), [
        'code' => 'LIVW'.substr(uniqid(), -6),
        'name' => 'viewer family',
    ]);

    get(route('grp.trade_units.units.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('is_checkbox', false)
            ->where('bulk_edit', null)
            ->etc());

    get(route('grp.trade_units.families.show', [$tradeUnitFamily->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bulk_edit', null)
            ->etc());

    $this->patch(route('grp.models.trade_units.bulk_update_label_info'), [
        ...$bulkLabelInfo,
        'trade_units' => [$this->bottle->id],
    ])->assertForbidden();

    $this->patch(route('grp.models.trade_units.bulk_update_gpsr'), [
        ...$bulkGpsr,
        'trade_units' => [$this->bottle->id],
    ])->assertForbidden();

    expect(data_get($this->bottle->refresh()->label_info, 'label_info_approved'))->toBeNull()
        ->and($this->bottle->gpsr_manufacturer)->not->toBe('Ancient Wisdom, Sheffield');
})->with('bulkLabelInfo', 'bulkGpsr');

test('label info and gpsr changes are recorded in the trade unit history', function (array $bulkLabelInfo, array $bulkGpsr) {
    $this->patch(route('grp.models.trade_units.bulk_update_label_info'), [
        ...$bulkLabelInfo,
        'trade_units' => [$this->bottle->id],
    ])->assertSessionHasNoErrors();

    $this->patch(route('grp.models.trade_units.bulk_update_gpsr'), [
        ...$bulkGpsr,
        'trade_units' => [$this->bottle->id],
    ])->assertSessionHasNoErrors();

    $audits = $this->bottle->refresh()->audits()->get();

    $labelInfoAudit = $audits->first(fn ($audit) => Arr::has($audit->new_values, 'label_info.markets'));
    $gpsrAudit      = $audits->first(fn ($audit) => Arr::has($audit->new_values, 'gpsr_manufacturer'));

    expect($labelInfoAudit)->not->toBeNull()
        ->and($labelInfoAudit->new_values)->toMatchArray([
            'label_info.markets'                        => 'uk, eu',
            'label_info.label_info_approved'            => true,
            'label_info.packaging_material_codes.value' => 'pap_20',
        ])
        ->and($labelInfoAudit->new_values)->not->toHaveKey('label_info')
        ->and($labelInfoAudit->old_values['label_info.markets'])->toBeNull()
        ->and($gpsrAudit)->not->toBeNull()
        ->and($gpsrAudit->new_values['gpsr_manufacturer'])->toBe('Ancient Wisdom, Sheffield')
        ->and($gpsrAudit->new_values['pictogram_flammable'])->toBeTruthy();

    get(route('grp.trade_units.units.show', [$this->bottle->slug, 'tab' => 'history']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tabs.current', 'history')
            ->has('history.data', fn ($histories) => $histories->etc())
            ->etc());
})->with('bulkLabelInfo', 'bulkGpsr');

test('the trade unit family page offers the bulk edit label info form', function () {
    $tradeUnitFamily = StoreTradeUnitFamily::make()->action(group(), [
        'code' => 'LIFAM'.substr(uniqid(), -6),
        'name' => 'label info family',
    ]);

    get(route('grp.trade_units.families.show', [$tradeUnitFamily->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bulk_edit.sections.label_info.updateRoute.name', 'grp.models.trade_units.bulk_update_label_info')
            ->where('bulk_edit.sections.label_info.fields.label_info_approved.value', false)
            ->etc());
});

test('the trade unit edit form offers the publish toggle switched off by default', function () {
    $publishToggle = fn ($blueprint) => data_get(
        collect($blueprint)->firstWhere('label', 'Labeling & Compliance Marks'),
        'fields.label_info_approved'
    );

    get(route('grp.trade_units.units.edit', [$this->bottle->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('formData.blueprint', fn ($blueprint) => data_get($publishToggle($blueprint), 'type') === 'toggle'
                && data_get($publishToggle($blueprint), 'value') === false)
            ->etc());

    UpdateTradeUnit::make()->action($this->bottle, ['label_info_approved' => true]);

    get(route('grp.trade_units.units.edit', [$this->bottle->refresh()->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('formData.blueprint', fn ($blueprint) => data_get($publishToggle($blueprint), 'value') === true)
            ->etc());
});

test('a tag attached to a trade unit reaches its products', function () {
    $tag = Tag::create([
        'group_id' => $this->group->id,
        'name'     => 'Made In '.uniqid(),
        'scope'    => TagScopeEnum::PRODUCT_PROPERTY,
    ]);

    AttachTagsToModel::make()->action($this->bottle, ['tags_id' => [$tag->id]]);

    expect($this->product->refresh()->tags->pluck('id'))->toContain($tag->id);
});

test('a product takes the tags of the trade units it is made of', function () {
    $tag = Tag::create([
        'group_id' => $this->group->id,
        'name'     => 'Made In '.uniqid(),
        'scope'    => TagScopeEnum::PRODUCT_PROPERTY,
    ]);
    $this->plug->tags()->attach($tag->id);

    SyncProductTradeUnits::run($this->product->refresh(), [['id' => $this->bottle->id, 'quantity' => 1]]);
    expect($this->product->refresh()->tags->pluck('id'))->not->toContain($tag->id);

    SyncProductTradeUnits::run($this->product->refresh(), [['id' => $this->plug->id, 'quantity' => 1]]);
    expect($this->product->refresh()->tags->pluck('id'))->toContain($tag->id);
});

test('product and master product pages send the slug of their trade unit', function () {
    $tradeUnitSlugs = [$this->bottle->refresh()->slug, $this->plug->refresh()->slug];
    $isTradeUnitSlug = fn ($slug) => in_array($slug, $tradeUnitSlugs, true);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->product->shop->slug,
        $this->product->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('trade_unit_slug', $isTradeUnitSlug)
        ->etc());

    get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterAsset->masterShop->slug,
        $this->masterAsset->refresh()->slug,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('trade_unit_slug', $isTradeUnitSlug)
        ->etc());
});
