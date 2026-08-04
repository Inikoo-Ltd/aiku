<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Facades\DB;

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

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'MMS'.substr(uniqid(), -6),
        'name' => 'Mismatch Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'MD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly(['master_shop_id' => $this->masterShop->id]);
    $this->tradeUnitId = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;

    $this->masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'    => 'MM-AST',
        'name'    => 'mismatch asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $this->masterAsset->id,
        'trade_unit_id' => $this->tradeUnitId,
        'quantity'      => 3,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $currencyCode = $this->shop->currency->code;
    $this->masterAsset->updateQuietly([
        'master_prices' => [$currencyCode => ['value' => '10', 'independent' => false]],
    ]);
});

function mismatchTestProduct($shop, $masterAsset, int $tradeUnitId, float $pivotQuantity, float $price): Product
{
    [, $seed] = createProduct($shop);

    $product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'        => 'MP'.substr(uniqid(), -8),
            'price'       => $price,
            'trade_units' => [['id' => $tradeUnitId, 'quantity' => $pivotQuantity]],
        ])
    );

    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'is_for_sale'       => true,
    ]);

    DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $product->id)->delete();
    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'Product',
        'model_id'      => $product->id,
        'trade_unit_id' => $tradeUnitId,
        'quantity'      => $pivotQuantity,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return $product;
}

test('flags composition and price deviations, honours opt-out flags, master flag survives a matching sibling', function () {
    $matching = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);

    $wrongComposition = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 10);

    $wrongPrice = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 12);

    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    $lastMatching = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);

    MasterAssetHydrateMismatch::run($this->masterAsset->refresh());

    expect($matching->refresh()->mismatch_with_master_detected)->toBeFalse()
        ->and($wrongComposition->refresh()->mismatch_with_master_detected)->toBeTrue()
        ->and($wrongPrice->refresh()->mismatch_with_master_detected)->toBeTrue()
        ->and($rebel->refresh()->mismatch_with_master_detected)->toBeFalse()
        ->and($lastMatching->refresh()->mismatch_with_master_detected)->toBeFalse()
        ->and($this->masterAsset->refresh()->mismatch_detected)->toBeTrue();
});

test('fix anomalies copies master composition and prices to following children only', function () {
    $wrong = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);

    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    \App\Actions\Masters\MasterAsset\FixMasterAssetAnomaliesFromMaster::make()->handle($this->masterAsset->refresh());

    $wrongQuantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $wrong->id)->value('quantity');
    $rebelQuantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebel->id)->value('quantity');

    expect((float)$wrongQuantity)->toBe(3.0)
        ->and((float)$rebelQuantity)->toBe(6.0)
        ->and((float)$wrong->refresh()->price)->toBe(10.0)
        ->and((float)$rebel->refresh()->price)->toBe(12.0)
        ->and($wrong->mismatch_with_master_detected)->toBeFalse()
        ->and($rebel->refresh()->mismatch_with_master_detected)->toBeFalse();
});

test('killing a rebel clears its opt-outs and fixes it from the master', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    \App\Actions\Masters\MasterAsset\KillMasterAssetRebelProduct::make()->handle($this->masterAsset->refresh(), $rebel);

    $quantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebel->id)->value('quantity');

    $rebel->refresh();
    expect($rebel->not_follow_master_trade_units)->toBeFalse()
        ->and($rebel->not_follow_master_prices)->toBeFalse()
        ->and((float)$quantity)->toBe(3.0)
        ->and((float)$rebel->price)->toBe(10.0)
        ->and($rebel->mismatch_with_master_detected)->toBeFalse();
});

test('a trade units scoped kill leaves the price rebellion untouched', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    \App\Actions\Masters\MasterAsset\KillMasterAssetRebelProduct::make()->handle($this->masterAsset->refresh(), $rebel, 'trade_units');

    $quantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebel->id)->value('quantity');

    $rebel->refresh();
    expect($rebel->not_follow_master_trade_units)->toBeFalse()
        ->and($rebel->not_follow_master_prices)->toBeTrue()
        ->and((float)$quantity)->toBe(3.0)
        ->and((float)$rebel->price)->toBe(12.0);
});

test('a prices scoped kill leaves the composition rebellion untouched', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    \App\Actions\Masters\MasterAsset\KillMasterAssetRebelProduct::make()->handle($this->masterAsset->refresh(), $rebel, 'prices');

    $quantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebel->id)->value('quantity');

    $rebel->refresh();
    expect($rebel->not_follow_master_trade_units)->toBeTrue()
        ->and($rebel->not_follow_master_prices)->toBeFalse()
        ->and((float)$quantity)->toBe(6.0)
        ->and((float)$rebel->price)->toBe(10.0);
});

test('kill all rebels clears every opt-out and fixes everything from the master', function () {
    $rebelA = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebelA->updateQuietly(['not_follow_master_trade_units' => true]);
    $rebelB = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 15);
    $rebelB->updateQuietly(['not_follow_master_prices' => true]);

    \App\Actions\Masters\MasterAsset\KillAllMasterAssetRebelProducts::make()->handle($this->masterAsset->refresh());

    $quantityA = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebelA->id)->value('quantity');

    expect($rebelA->refresh()->not_follow_master_trade_units)->toBeFalse()
        ->and((float)$quantityA)->toBe(3.0)
        ->and($rebelB->refresh()->not_follow_master_prices)->toBeFalse()
        ->and((float)$rebelB->price)->toBe(10.0)
        ->and($this->masterAsset->refresh()->mismatch_detected)->toBeFalse();
});

test('detects warehouse picking that differs from the master', function () {
    $stocks = createStocks($this->group);
    $stock  = $stocks[0];
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);

    DB::table('master_asset_has_stocks')->updateOrInsert(
        ['master_asset_id' => $this->masterAsset->id, 'stock_id' => $stock->id],
        ['quantity' => 3, 'created_at' => now(), 'updated_at' => now()]
    );

    $product = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);
    DB::table('product_has_org_stocks')->updateOrInsert(
        ['product_id' => $product->id, 'org_stock_id' => $orgStock->id],
        ['quantity' => 6]
    );

    $anomalies = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());

    expect($anomalies)->toHaveKey($product->id)
        ->and($anomalies[$product->id]['issues'])->toHaveCount(1)
        ->and($anomalies[$product->id]['issues'][0])->toContain('picks 6')
        ->and($anomalies[$product->id]['issues'][0])->toContain('master says 3');

    $product->updateQuietly(['not_follow_master_trade_units' => true]);
    $anomalies = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());

    expect($anomalies[$product->id]['issues'])->toBeEmpty()
        ->and($anomalies[$product->id]['ignored_issues'])->toHaveCount(1);
});

test('kill rebel endpoint redirects back for Inertia requests', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly(['not_follow_master_trade_units' => true]);

    $response = \Pest\Laravel\from('/previous-page')->post(
        route('grp.models.master_asset.kill_rebel', [
            'masterAsset' => $this->masterAsset->id,
            'product'     => $rebel->id,
        ]),
        [],
        ['X-Inertia' => 'true']
    );

    $response->assertRedirect('/previous-page');
    expect($rebel->refresh()->not_follow_master_trade_units)->toBeFalse();
});

test('a rebel is listed even when its values currently match the master', function () {
    $compositionRebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);
    $compositionRebel->updateQuietly(['not_follow_master_trade_units' => true]);

    $priceRebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);
    $priceRebel->updateQuietly(['not_follow_master_prices' => true]);

    $anomalies = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());

    expect($anomalies[$compositionRebel->id]['issues'])->toBeEmpty()
        ->and($anomalies[$compositionRebel->id]['ignored_issues'][0])->toContain('currently identical to master')
        ->and($anomalies[$compositionRebel->id]['ignored_scopes'])->toBe(['trade_units'])
        ->and($anomalies[$priceRebel->id]['ignored_issues'][0])->toContain('price and RRP')
        ->and($anomalies[$priceRebel->id]['ignored_scopes'])->toBe(['prices'])
        ->and($compositionRebel->refresh()->mismatch_with_master_detected)->toBeFalsy();
});

test('killing a composition rebel re-syncs its org stock picking, not just the trade unit pivot', function () {
    $stocks = createStocks($this->group);
    $stock  = $stocks[0];
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);
    $tradeUnit = $stock->tradeUnits()->first();

    DB::table('model_has_trade_units')->where('model_type', 'MasterAsset')->where('model_id', $this->masterAsset->id)->delete();
    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $this->masterAsset->id,
        'trade_unit_id' => $tradeUnit->id,
        'quantity'      => 3,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
    DB::table('master_asset_has_stocks')->updateOrInsert(
        ['master_asset_id' => $this->masterAsset->id, 'stock_id' => $stock->id],
        ['quantity' => 3, 'created_at' => now(), 'updated_at' => now()]
    );

    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $tradeUnit->id, 6, 10);
    DB::table('product_has_org_stocks')->updateOrInsert(
        ['product_id' => $rebel->id, 'org_stock_id' => $orgStock->id],
        ['quantity' => 6]
    );
    $rebel->updateQuietly(['not_follow_master_trade_units' => true]);

    $before = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());
    expect($before[$rebel->id]['ignored_issues'])->not->toBeEmpty();

    App\Actions\Masters\MasterAsset\KillMasterAssetRebelProduct::make()
        ->handle($this->masterAsset->refresh(), $rebel, 'trade_units');

    $pivotQuantity = (float) DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $rebel->id)->value('quantity');
    $pickQuantity = (float) DB::table('product_has_org_stocks')
        ->where('product_id', $rebel->id)->value('quantity');

    expect($pivotQuantity)->toBe(3.0)
        ->and($pickQuantity)->toBe(3.0)
        ->and(App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh()))->toBeEmpty();
});

test('all products aligned clears the flags', function () {
    $product = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);
    $product->updateQuietly(['mismatch_with_master_detected' => true]);
    $this->masterAsset->updateQuietly(['mismatch_detected' => true]);

    MasterAssetHydrateMismatch::run($this->masterAsset->refresh());

    expect($product->refresh()->mismatch_with_master_detected)->toBeFalse()
        ->and($this->masterAsset->refresh()->mismatch_detected)->toBeFalse();
});

test('the follow-master toggles save on confirmation instead of waiting for a separate save click', function () {
    $product = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);

    $blueprint = \App\Actions\Catalogue\Product\UI\EditProduct::make()->getBlueprint($product->refresh());

    $fields = collect($blueprint)->pluck('fields')->reduce(
        fn ($carry, $sectionFields) => array_merge($carry ?? [], $sectionFields ?? []),
        []
    );

    expect($fields['not_follow_master_prices']['submitOnConfirm'] ?? false)->toBeTrue()
        ->and($fields['not_follow_master_prices']['warningText'] ?? null)->not->toBeNull()
        ->and($fields['not_follow_master_trade_units']['submitOnConfirm'] ?? false)->toBeTrue()
        ->and($fields['not_follow_master_trade_units']['warningText'] ?? null)->not->toBeNull();
});

test('a shop that opts out of master pricing is a rebellion, not a fixable anomaly', function () {
    $product = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 12);

    $settings = $this->shop->settings;
    data_set($settings, 'catalog.follow_master_pricing', false);
    $this->shop->updateQuietly(['settings' => $settings]);

    $anomalies = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());

    expect($anomalies[$product->id]['issues'])->toBeEmpty()
        ->and($anomalies[$product->id]['ignored_issues'])->not->toBeEmpty()
        ->and($anomalies[$product->id]['ignored_scopes'])->not->toContain('prices');
});

test('a big master queues the fan out and chains the mismatch hydration after it', function () {
    Illuminate\Support\Facades\Bus::fake();

    foreach (range(1, 6) as $ignored) {
        mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    }

    App\Actions\Masters\MasterAsset\FixMasterAssetAnomaliesFromMaster::make()->handle($this->masterAsset->refresh());

    Illuminate\Support\Facades\Bus::assertDispatched(
        Lorisleiva\Actions\Decorators\JobDecorator::class,
        function ($job) {
            $chainedActions = collect($job->chained)
                ->map(fn ($chained) => get_class(unserialize($chained)->getAction()))
                ->all();

            return $job->decorates(App\Actions\Masters\MasterAsset\FixProductTradeUnitsFromMaster::class)
                && $chainedActions === [
                    App\Actions\Masters\MasterAsset\CascadeMasterAssetPricesToChildren::class,
                    App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch::class,
                ];
        }
    );
});

test('killing a rebel writes an audit record naming the flags that changed', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    App\Actions\Masters\MasterAsset\KillMasterAssetRebelProduct::make()
        ->handle($this->masterAsset->refresh(), $rebel, 'trade_units');

    $audit = $rebel->audits()->where('event', 'killed_rebel')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values)->toHaveKey('not_follow_master_trade_units')
        ->and($audit->new_values['not_follow_master_trade_units'])->toBeFalse()
        ->and($audit->new_values)->not->toHaveKey('not_follow_master_prices');
});

test('the family mismatch flag is cleared once no master in it mismatches', function () {
    $wrong = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 10);

    MasterAssetHydrateMismatch::run($this->masterAsset->refresh());
    expect($this->masterFamily->refresh()->mismatch_detected)->toBeTrue();

    DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $wrong->id)
        ->update(['quantity' => 3]);

    MasterAssetHydrateMismatch::run($this->masterAsset->refresh());

    expect($this->masterFamily->refresh()->mismatch_detected)->toBeFalse();
});

test('kill rebel is refused for a product belonging to another master', function () {
    $otherMaster = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'    => 'MM-OTHER',
        'name'    => 'other master',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $foreignProduct = mismatchTestProduct($this->shop, $otherMaster, $this->tradeUnitId, 6, 12);
    $foreignProduct->updateQuietly(['not_follow_master_trade_units' => true]);

    $response = \Pest\Laravel\post(
        route('grp.models.master_asset.kill_rebel', [
            'masterAsset' => $this->masterAsset->id,
            'product'     => $foreignProduct->id,
        ]),
        []
    );

    $response->assertSessionHasErrors('product');
    expect($foreignProduct->refresh()->not_follow_master_trade_units)->toBeTrue();
});

test('kill rebel rejects an unknown scope instead of widening it', function () {
    $rebel = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $rebel->updateQuietly([
        'not_follow_master_trade_units' => true,
        'not_follow_master_prices'      => true,
    ]);

    $response = \Pest\Laravel\post(
        route('grp.models.master_asset.kill_rebel', [
            'masterAsset' => $this->masterAsset->id,
            'product'     => $rebel->id,
        ]),
        ['scope' => 'everything']
    );

    $response->assertSessionHasErrors('scope');
    $rebel->refresh();
    expect($rebel->not_follow_master_trade_units)->toBeTrue()
        ->and($rebel->not_follow_master_prices)->toBeTrue();
});

test('discontinued products are neither flagged nor fixed', function () {
    $live         = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $discontinued = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 12);
    $discontinued->updateQuietly([
        'status'                        => App\Enums\Catalogue\Product\ProductStatusEnum::DISCONTINUED,
        'mismatch_with_master_detected' => true,
    ]);

    $anomalies = App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies::run($this->masterAsset->refresh());

    expect($anomalies)->toHaveKey($live->id)
        ->and($anomalies)->not->toHaveKey($discontinued->id);

    MasterAssetHydrateMismatch::run($this->masterAsset->refresh());
    expect($discontinued->refresh()->mismatch_with_master_detected)->toBeFalse();

    App\Actions\Masters\MasterAsset\FixProductTradeUnitsFromMaster::run($this->masterAsset->refresh());

    $discontinuedQuantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $discontinued->id)->value('quantity');
    $liveQuantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $live->id)->value('quantity');

    expect((float)$discontinuedQuantity)->toBe(6.0)
        ->and((float)$liveQuantity)->toBe(3.0);
});

test('the organisation composition fixer aligns drifted shops and reports before applying', function () {
    $drifted = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 6, 10);
    $drifted->updateQuietly(['units' => 6]);
    $this->masterAsset->updateQuietly(['units' => 3]);

    $aligned = mismatchTestProduct($this->shop, $this->masterAsset, $this->tradeUnitId, 3, 10);
    $aligned->updateQuietly(['units' => 3]);

    $quantityOf = fn ($product) => (float)DB::table('model_has_trade_units')
        ->where('model_type', 'Product')->where('model_id', $product->id)->value('quantity');

    // The suite shares a database, so assert on these products rather than on totals.
    $dry = App\Actions\Masters\MasterAsset\FixOrganisationCompositionFromMasters::run($this->organisation, dryRun: true);

    expect($dry['changes'])->toContain($this->masterAsset->code.' @ '.$this->shop->code.' (units 6 → 3)')
        ->and($quantityOf($drifted))->toBe(6.0)
        ->and((float)$drifted->refresh()->units)->toBe(6.0);

    App\Actions\Masters\MasterAsset\FixOrganisationCompositionFromMasters::run($this->organisation, dryRun: false, withUnits: true);

    expect($quantityOf($drifted))->toBe(3.0)
        ->and((float)$drifted->refresh()->units)->toBe(3.0)
        ->and($quantityOf($aligned))->toBe(3.0)
        ->and((float)$aligned->refresh()->units)->toBe(3.0)
        ->and($drifted->audits()->where('event', 'units_aligned_to_master')->exists())->toBeTrue()
        ->and($aligned->audits()->where('event', 'units_aligned_to_master')->exists())->toBeFalse();
});
