<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Facades\Artisan;
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
        'code' => 'CMS'.substr(uniqid(), -6),
        'name' => 'Composition Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'CD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'CF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly(['master_shop_id' => $this->masterShop->id]);
    $this->tradeUnitId      = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
    $this->otherTradeUnitId = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
});

function compositionTestMaster($masterFamily, int $tradeUnitId, float $units, string $code)
{
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => $code,
        'name'    => 'composition asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);
    $masterAsset->updateQuietly(['units' => $units]);

    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $masterAsset->id,
        'trade_unit_id' => $tradeUnitId,
        'quantity'      => $units,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return $masterAsset;
}

function compositionTestChild($shop, $masterAsset, int $tradeUnitId, float $units, float $pivotQuantity)
{
    [, $seed] = createProduct($shop);

    $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action(
        $seed->family,
        array_merge(\App\Models\Catalogue\Product::factory()->definition(), [
            'code'        => 'CP'.substr(uniqid(), -8),
            'price'       => 10,
            'trade_units' => [['id' => $tradeUnitId, 'quantity' => $pivotQuantity]],
        ])
    );

    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'units'             => $units,
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

function compositionPivotQuantity(int $productId): ?float
{
    $quantity = DB::table('model_has_trade_units')
        ->where('model_type', 'Product')
        ->where('model_id', $productId)
        ->value('quantity');

    return $quantity === null ? null : (float) $quantity;
}

test('unifies a genuinely different pack with its master and leaves the stale scalar case alone', function () {
    $masterAsset = compositionTestMaster($this->masterFamily, $this->tradeUnitId, 4, 'COMP-AST');

    // Majority of siblings agree with the master and its composition.
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);

    // Units and composition agree with each other and dissent from the master: this is job B.
    $differentPack = compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 1);

    // Only the scalar is stale, its composition already says 4: that belongs to repair:master_child_units.
    $staleScalar = compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 4);

    Artisan::call('repair:master_child_trade_unit_composition', ['master_shop' => $this->masterShop->slug]);

    expect(compositionPivotQuantity($differentPack->id))->toBe(1.0)
        ->and((float) $differentPack->refresh()->units)->toBe(1.0);

    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--fix'       => true,
    ]);

    expect(compositionPivotQuantity($differentPack->id))->toBe(4.0)
        ->and((float) $differentPack->refresh()->units)->toBe(4.0);

    // The stale-scalar product is job A's territory and must not be touched here.
    expect(compositionPivotQuantity($staleScalar->id))->toBe(4.0)
        ->and((float) $staleScalar->refresh()->units)->toBe(1.0);

    // Idempotent: a second run has nothing left to do and changes nothing.
    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--fix'       => true,
    ]);

    expect(compositionPivotQuantity($differentPack->id))->toBe(4.0)
        ->and((float) $staleScalar->refresh()->units)->toBe(1.0);
});

test('never rewrites a product built from a different trade unit than its master', function () {
    $masterAsset = compositionTestMaster($this->masterFamily, $this->tradeUnitId, 4, 'COMP-TU');

    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);

    $foreignTradeUnit = compositionTestChild($this->shop, $masterAsset, $this->otherTradeUnitId, 1, 1);

    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--fix'       => true,
    ]);

    expect(compositionPivotQuantity($foreignTradeUnit->id))->toBe(1.0)
        ->and((float) $foreignTradeUnit->refresh()->units)->toBe(1.0)
        ->and(DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->where('model_id', $foreignTradeUnit->id)
            ->value('trade_unit_id'))->toBe($this->otherTradeUnitId);
});

test('leaves a master alone when its children are tied and there is no real majority', function () {
    $masterAsset = compositionTestMaster($this->masterFamily, $this->tradeUnitId, 4, 'COMP-TIE');

    // One child each way: the master's value is not a majority, it just sorts first.
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    $tied = compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 1);

    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--fix'       => true,
    ]);

    expect(compositionPivotQuantity($tied->id))->toBe(1.0)
        ->and((float) $tied->refresh()->units)->toBe(1.0);
});

test('shop option narrows which deviants are corrected without breaking the majority', function () {
    $masterAsset = compositionTestMaster($this->masterFamily, $this->tradeUnitId, 4, 'COMP-SHOP');

    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    $deviant = compositionTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 1);

    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--shop'      => 'NOSUCHSHOP',
        '--fix'       => true,
    ]);
    expect(compositionPivotQuantity($deviant->id))->toBe(1.0);

    Artisan::call('repair:master_child_trade_unit_composition', [
        'master_shop' => $this->masterShop->slug,
        '--shop'      => $this->shop->code,
        '--fix'       => true,
    ]);
    expect(compositionPivotQuantity($deviant->id))->toBe(4.0);
});
