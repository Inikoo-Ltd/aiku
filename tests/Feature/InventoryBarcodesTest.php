<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Inventory\OrgStock\FillOrgStockWithTradeUnitsBarcodes;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use Config;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/InventoryHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        setPermissionsTeamId($this->group->id);
        $this->guest = createAdminGuest($this->group);

        $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

        Config::set("inertia.pages.paths", [resource_path("js/Pages/Grp")]);
        $this->user = $this->guest->getUser();
        actingAs($this->user);
    }
);

test('org stock indexes use time series aggregation', function () {
    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    createWarehouse();
    $stocks = createStocks($this->group);
    createOrgStocks($this->organisation, $stocks);

    $organisation = $this->organisation;

    $indexOrgStocks = \App\Actions\Inventory\OrgStock\UI\IndexOrgStocks::make();
    (function () use ($organisation) {
        $this->organisation = $organisation;
    })->call($indexOrgStocks);

    $indexOrgStocksWithNoProducts = \App\Actions\Inventory\OrgStock\UI\IndexOrgStocksWithNoProducts::make();
    (function () use ($organisation) {
        $this->organisation = $organisation;
    })->call($indexOrgStocksWithNoProducts);

    expect($indexOrgStocks->handle($this->organisation, bucket: 'all')->total())->toBeGreaterThanOrEqual(1)
        ->and($indexOrgStocksWithNoProducts->handle($this->organisation, bucket: 'all')->total())->toBeGreaterThanOrEqual(0);
});

test('fill org stock barcode from its single trade unit', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $tradeUnit->update(['barcode' => '5050000000017']);

    $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);

    expect(FillOrgStockWithTradeUnitsBarcodes::run($orgStock->refresh()))->toBe('5050000000017')
        ->and($orgStock->refresh()->unit_barcode)->toBe('5050000000017')
        ->and($orgStock->barcode)->toBeNull();
});

test('guess a barcode for org stocks holding several copies of one trade unit', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $tradeUnit->update(['barcode' => '5050000000048']);

    $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 6]]);

    expect(FillOrgStockWithTradeUnitsBarcodes::run($orgStock->refresh()))->toBe('5050000000048');
});

test('do not guess a barcode for org stocks that are not a single trade unit', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $tradeUnit->update(['barcode' => '5050000000024']);

    $otherTradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $orgStock->tradeUnits()->sync([
        $tradeUnit->id      => ['quantity' => 1],
        $otherTradeUnit->id => ['quantity' => 1],
    ]);
    expect(FillOrgStockWithTradeUnitsBarcodes::run($orgStock->refresh()))->toBeNull();
});

test('do not guess a barcode shared by two org stocks of the same organisation', function () {
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $tradeUnit->update(['barcode' => '5050000000031']);

    $orgStocks = collect([1, 2])->map(function () use ($tradeUnit) {
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
            'state' => StockStateEnum::ACTIVE
        ]));

        $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
        $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);

        return $orgStock->refresh();
    });

    expect(FillOrgStockWithTradeUnitsBarcodes::run($orgStocks->first()))->toBeNull()
        ->and(FillOrgStockWithTradeUnitsBarcodes::run($orgStocks->last()))->toBeNull();
});

test('changing a trade unit barcode refreshes the unit_barcode of its single-trade-unit org stocks', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());

    $orgStock->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);
    $orgStock->update(['is_single_trade_unit' => true, 'unit_barcode' => 'OLD-EAN']);

    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['barcode' => '5055796528387']);
    expect($orgStock->refresh()->unit_barcode)->toBe('5055796528387');

    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['barcode' => null]);
    expect($orgStock->refresh()->unit_barcode)->toBeNull();
});

test('editing an org stock sko barcode writes through the stock to every sibling org stock', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm2');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $organisation2 = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
    $sibling = StoreOrgStock::make()->action($organisation2, $stock);

    $orgStock = UpdateOrgStock::make()->action($orgStock, ['barcode' => ' 5050000000055 ']);
    expect($orgStock->barcode)->toBe('5050000000055')
        ->and($stock->refresh()->barcode)->toBe('5050000000055')
        ->and($sibling->refresh()->barcode)->toBe('5050000000055');

    $otherStock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $otherOrgStock = StoreOrgStock::make()->action($this->organisation, $otherStock);

    expect(fn () => UpdateOrgStock::make()->action($otherOrgStock, ['barcode' => '5050000000055']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $siblingOfTheSameStockIsNotAConflict = UpdateOrgStock::make()->action($sibling, ['barcode' => '5050000000055']);
    expect($siblingOfTheSameStockIsNotAConflict->barcode)->toBe('5050000000055');

    $orgStock = UpdateOrgStock::make()->action($orgStock, ['barcode' => null]);
    expect($orgStock->barcode)->toBeNull()
        ->and($orgStock->independent_barcode)->toBeFalse()
        ->and($stock->refresh()->barcode)->toBeNull()
        ->and($sibling->refresh()->barcode)->toBeNull()
        ->and($sibling->refresh()->independent_barcode)->toBeFalse();
});

test('a new org stock is born holding the sko barcode its stock already carries', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
    UpdateOrgStock::make()->action($orgStock, ['barcode' => '5050000000123']);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm3');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $organisation3 = \App\Models\SysAdmin\Organisation::where('code', 'acm3')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);

    $newcomer = StoreOrgStock::make()->action($organisation3, $stock->refresh());

    expect($newcomer->barcode)->toBe('5050000000123')
        ->and($newcomer->independent_barcode)->toBeTrue();
});

test('sko barcode repair pushes the majority barcode up to the stock and across all siblings', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm2');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $organisation2 = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
    $sibling = StoreOrgStock::make()->action($organisation2, $stock);

    $orgStock->updateQuietly(['barcode' => '5050000000109']);
    $sibling->updateQuietly(['barcode' => '5050000000109N']);

    $repair                                = new \App\Actions\Goods\Stock\RepairStocksSkoBarcodes();
    $onATieOnUsesTheOldestOrgStockWins     = $repair->canonicalBarcode($stock);

    expect($onATieOnUsesTheOldestOrgStockWins)->toBe('5050000000109');

    $repair->handle($stock, $onATieOnUsesTheOldestOrgStockWins, true);

    expect($stock->refresh()->barcode)->toBe('5050000000109')
        ->and($orgStock->refresh()->barcode)->toBe('5050000000109')
        ->and($orgStock->independent_barcode)->toBeTrue()
        ->and($sibling->refresh()->barcode)->toBe('5050000000109');

    $eanStock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $eanOrgStock = StoreOrgStock::make()->action($this->organisation, $eanStock);
    $eanOrgStock->updateQuietly(['unit_barcode' => '5050000000116', 'packed_in' => 1]);

    expect($repair->unitBarcodeToCopy($eanStock->refresh()))->toBe('5050000000116');

    $eanOrgStock->updateQuietly(['packed_in' => 6]);
    expect($repair->unitBarcodeToCopy($eanStock->refresh()))->toBeNull();

    $eanOrgStock->updateQuietly(['packed_in' => 1]);
    $orgStock->updateQuietly(['barcode' => '5050000000116']);

    expect($repair->conflictingHolder($eanStock, '5050000000116'))->toBe("stock id $stock->id");
});

test('a discontinued org stock neither wins the barcode ballot nor vetoes the unit ean copy', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $live = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm2');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $organisation2 = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
    $dead = StoreOrgStock::make()->action($organisation2, $stock);

    $repair = new \App\Actions\Goods\Stock\RepairStocksSkoBarcodes();

    $dead->updateQuietly([
        'barcode'    => 'DEAD-BARCODE',
        'state'      => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUED,
        'packed_in'  => 12,
    ]);
    $live->updateQuietly(['barcode' => 'LIVE-BARCODE', 'packed_in' => 1]);

    expect($repair->canonicalBarcode($stock->refresh()))->toBe('LIVE-BARCODE');

    $live->updateQuietly(['barcode' => null, 'unit_barcode' => '5050000000147']);
    $dead->updateQuietly(['barcode' => null, 'unit_barcode' => '5050000000154']);

    expect($repair->unitBarcodeToCopy($stock->refresh()))->toBe('5050000000147');
});

test('cascading a sko barcode leaves a history entry on the siblings it changed', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgData = \App\Models\SysAdmin\Organisation::factory()->definition();
    data_set($orgData, 'code', 'acm2');
    data_set($orgData, 'type', \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP);
    $organisation2 = \App\Models\SysAdmin\Organisation::where('code', 'acm2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action($this->group, $orgData);
    $sibling = StoreOrgStock::make()->action($organisation2, $stock);

    UpdateOrgStock::make()->action($orgStock, ['barcode' => '5050000000161']);

    $siblingAudit = $sibling->audits()->get()->last();

    expect($sibling->refresh()->barcode)->toBe('5050000000161')
        ->and($siblingAudit)->not->toBeNull()
        ->and(data_get($siblingAudit->new_values, 'barcode'))->toBe('5050000000161');
});

test('two stocks in a group cannot answer to the same sko barcode', function () {
    $first = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $second = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    $first->update(['barcode' => '5050000000178']);

    expect(fn () => $second->update(['barcode' => '5050000000178']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('a stock duplicated inside one organisation gives its barcode to a single org stock', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    $original  = StoreOrgStock::make()->action($this->organisation, $stock);
    $duplicate = StoreOrgStock::make()->action($this->organisation, $stock);

    expect($duplicate->organisation_id)->toBe($original->organisation_id);

    $repair = new \App\Actions\Goods\Stock\RepairStocksSkoBarcodes();

    $nobodyIsPickedAsTheWinner = $repair::orgStocksToCarryBarcode($stock->refresh(), '5050000000185');

    expect($nobodyIsPickedAsTheWinner)->toBeEmpty()
        ->and($repair::organisationsWithDuplicates($stock))->toContain($original->organisation_id);

    $repair->handle($stock, '5050000000185', true);

    expect($stock->refresh()->barcode)->toBe('5050000000185')
        ->and($original->refresh()->barcode)->toBeNull()
        ->and($duplicate->refresh()->barcode)->toBeNull();

    $original->updateQuietly(['barcode' => '5050000000185']);

    $theTwinAlreadyScannedKeepsIt = $repair::orgStocksToCarryBarcode($stock->refresh(), '5050000000185');

    expect($theTwinAlreadyScannedKeepsIt->pluck('id')->all())->toBe([$original->id]);

    UpdateOrgStock::make()->action($original->refresh(), ['barcode' => '5050000000192']);

    expect($original->refresh()->barcode)->toBe('5050000000192')
        ->and($duplicate->refresh()->barcode)->toBeNull();
});

test('sko barcode repair treats an orphan org stock holding the barcode as a conflict', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orphan = StoreOrgStock::make()->action($this->organisation, $stock);
    $orphan->updateQuietly(['barcode' => '5050000000130', 'stock_id' => null]);

    $otherStock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));

    expect((new \App\Actions\Goods\Stock\RepairStocksSkoBarcodes())->conflictingHolder($otherStock, '5050000000130'))
        ->toBe("org stock id $orphan->id");
});

test('set org stock unit_barcode does not touch independent_barcode', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $orgStock = UpdateOrgStock::make()->action($orgStock, ['unit_barcode' => ' 5050000000062 ']);
    expect($orgStock->unit_barcode)->toBe('5050000000062')
        ->and($orgStock->independent_barcode)->toBeFalse()
        ->and($orgStock->barcode)->toBeNull();

    $orgStock = UpdateOrgStock::make()->action($orgStock, ['unit_barcode' => null]);
    expect($orgStock->unit_barcode)->toBeNull();
});

test('org stock carton barcode is kept once on the stock and shown as the third barcode', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    UpdateOrgStock::make()->action($orgStock, ['carton_barcode' => ' 5050000000062C ']);
    expect($stock->refresh()->carton_barcode)->toBe('5050000000062C')
        ->and(collect(\App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes::run($orgStock->refresh()))->pluck('number', 'level')->get('carton'))->toBe('5050000000062C')
        ->and(collect(\App\Actions\Goods\Stock\UI\GetStockShowcase::run($stock)['barcodes'])->pluck('level')->all())->toBe(['unit', 'sko', 'carton']);

    UpdateOrgStock::make()->action($orgStock->refresh(), ['carton_barcode' => '']);
    expect($stock->refresh()->carton_barcode)->toBeNull();
});

test('scan ignores the unit_barcode and matches only the sko barcode', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE
    ]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['unit_barcode' => '5050000000079', 'barcode' => 'SKO-5050']);

    $deliveryNoteItem = new \App\Models\Dispatching\DeliveryNoteItem();
    $deliveryNoteItem->org_stock_id = $orgStock->id;
    $deliveryNoteItem->setRelation('orgStock', $orgStock);

    $matcher = new class () {
        use \App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;

        public function match($items, $scanned)
        {
            return $this->matchItems($items, $scanned);
        }
    };

    expect($matcher->match(collect([$deliveryNoteItem]), '5050000000079'))->toBeEmpty()
        ->and($matcher->match(collect([$deliveryNoteItem]), 'SKO-5050')->first())->toBe($deliveryNoteItem);
});
