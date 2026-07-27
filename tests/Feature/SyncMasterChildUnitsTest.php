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
        'code' => 'UMS'.substr(uniqid(), -6),
        'name' => 'Units Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'UD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly(['master_shop_id' => $this->masterShop->id]);
    $this->tradeUnitId = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
});

/** A child of the master, with its own units scalar and its own trade-unit quantity. */
function unitsTestChild($shop, $masterAsset, int $tradeUnitId, float $units, float $pivotQuantity)
{
    [, $seed] = createProduct($shop);

    $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action(
        $seed->family,
        array_merge(\App\Models\Catalogue\Product::factory()->definition(), [
            'code'        => 'UP'.substr(uniqid(), -8),
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

test('fixes a stale units scalar but never a product whose composition really differs', function () {
    $masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'    => 'UNITS-AST',
        'name'    => 'units asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);
    $masterAsset->updateQuietly(['units' => 4]);
    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $masterAsset->id,
        'trade_unit_id' => $this->tradeUnitId,
        'quantity'      => 4,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    // Majority of siblings agree with the master and its composition.
    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);

    // Only the scalar is stale: its own composition already says 4.
    $staleScalar = unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 4);

    // Genuinely a different pack: units and composition agree with each other, not the master.
    // Correcting this one means changing the bill of materials, so it must be left alone.
    $differentPack = unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 1);

    Artisan::call('repair:master_child_units', ['master_shop' => $this->masterShop->slug]);
    expect((float) $staleScalar->refresh()->units)->toBe(1.0);

    Artisan::call('repair:master_child_units', ['master_shop' => $this->masterShop->slug, '--fix' => true]);

    expect((float) $staleScalar->refresh()->units)->toBe(4.0)
        ->and((float) $differentPack->refresh()->units)->toBe(1.0);

    // The bill of materials is untouched on both sides.
    expect((float) DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $staleScalar->id)->value('quantity'))->toBe(4.0)
        ->and((float) DB::table('model_has_trade_units')->where('model_type', 'Product')->where('model_id', $differentPack->id)->value('quantity'))->toBe(1.0);
});

test('shop option narrows which deviants are corrected without breaking the majority', function () {
    $masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'    => 'UNITS-SHOP',
        'name'    => 'units shop asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);
    $masterAsset->updateQuietly(['units' => 4]);
    DB::table('model_has_trade_units')->insert([
        'model_type'    => 'MasterAsset',
        'model_id'      => $masterAsset->id,
        'trade_unit_id' => $this->tradeUnitId,
        'quantity'      => 4,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 4, 4);
    $stale = unitsTestChild($this->shop, $masterAsset, $this->tradeUnitId, 1, 4);

    // A shop that owns none of them must correct nothing.
    Artisan::call('repair:master_child_units', [
        'master_shop' => $this->masterShop->slug,
        '--shop'      => 'NOSUCHSHOP',
        '--fix'       => true,
    ]);
    expect((float) $stale->refresh()->units)->toBe(1.0);

    Artisan::call('repair:master_child_units', [
        'master_shop' => $this->masterShop->slug,
        '--shop'      => $this->shop->code,
        '--fix'       => true,
    ]);
    expect((float) $stale->refresh()->units)->toBe(4.0);
});
