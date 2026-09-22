<?php

use App\Actions\Transfers\Aurora\WithFetchStock;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;

/**
 * This one genuinely needs rows: processOrgStock looks the org stock up before deciding, so it
 * lives with the feature tests and builds the shared organisation the way they do. It used to
 * sit in tests/Unit behind a hand-rolled transaction, which did not hold: the rollback left a
 * group behind carrying only 43 of its 45 job position categories, and every later test in that
 * worker died inside SeedJobPositions on a category that was never there.
 */
function auroraStockGuardHarness(): object
{
    return new class () {
        use WithFetchStock;

        public int $hydratorsDelay = 0;

        public function run(SourceOrganisationService $source, Stock $stock, array $stockData): ?OrgStock
        {
            return $this->processOrgStock($source, $stock, $stockData);
        }

        protected function recordError($source, $e, $data, $model, $operation): void
        {
        }

        protected function saveMigrationHistory($model, $data): void
        {
        }
    };
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
});

it('never lets aurora update an existing org stock, whichever organisation it belongs to', function (bool $aikuStockControl) {
    $unique       = uniqid();
    $organisation = $this->organisation;
    $organisation->update(['is_aiku_stock_control' => $aikuStockControl]);

    $stock = Stock::create([
        'group_id' => $organisation->group_id,
        'code'     => 'GUARD-'.$unique,
        'name'     => 'guard stock',
        'slug'     => 'guard-stock-'.$unique,
    ]);

    $orgStock = OrgStock::create([
        'group_id'        => $organisation->group_id,
        'organisation_id' => $organisation->id,
        'stock_id'        => $stock->id,
        'slug'            => 'guard-org-stock-'.$unique,
        'code'            => 'GUARD-'.$unique,
        'source_id'       => 'aurora:guard-'.$unique,
        'name'            => 'edited in aiku',
    ]);

    $source = Mockery::mock(SourceOrganisationService::class);
    $source->shouldReceive('getOrganisation')->andReturn($organisation);

    auroraStockGuardHarness()->run($source, $stock, [
        'stock'     => ['source_id' => 'aurora:guard-'.$unique, 'code' => $orgStock->code],
        'org_stock' => ['name' => 'overwritten by aurora', 'source_id' => 'aurora:guard-'.$unique],
    ]);

    expect($orgStock->refresh()->name)->toBe('edited in aiku');
})->with([
    'organisation already runs stock control in aiku' => [true],
    'organisation still follows aurora, eg aroma'     => [false],
]);

function auroraMovementRow(string $type, string $section = ''): object
{
    return (object) [
        'Inventory Transaction Key'         => 1,
        'Inventory Transaction Record Type' => 'Movement',
        'Inventory Transaction Type'        => $type,
        'Inventory Transaction Section'     => $section,
        'Inventory Transaction Quantity'    => 5,
        'Inventory Transaction Amount'      => 0,
        'Part SKU'                          => 'none',
        'Location Key'                      => 1,
        'Warehouse Key'                     => 1,
        'Note'                              => null,
        'Date'                              => '2026-01-01 00:00:00',
        'Part Location Stock'               => null,
        'aiku_picking_id'                   => null,
    ];
}

it('lets only production movements through from aurora when the organisation runs stock control in aiku', function (string $type, string $section, bool $reachesParsing, ?string $deliveryParent = null) {
    $organisation = $this->organisation;
    $organisation->update(['is_aiku_stock_control' => true]);

    $note = null;
    if ($deliveryParent) {
        DB::table('stock_deliveries')->insert([
            'group_id'        => $organisation->group_id,
            'organisation_id' => $organisation->id,
            'parent_type'     => 'Production',
            'parent_id'       => 1,
            'parent_code'     => 'guard',
            'date'            => now(),
            'data'            => '{}',
            'cost_data'       => '{}',
            'parent_name'     => 'guard',
            'currency_id'     => $organisation->currency_id,
            'slug'            => 'guard-'.uniqid(),
            'reference'       => 'guard',
            'source_id'       => $organisation->id.':777',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $note = 'delivery/777';
    }

    $source               = Mockery::mock(SourceOrganisationService::class);
    $source->organisation = $organisation;

    $fetcher = new class ($source) extends \App\Transfers\Aurora\FetchAuroraOrgStockMovement {
        public bool $lookedUpOrgStock = false;

        public function feed(object $row): void
        {
            $this->auroraModelData = $row;
            $this->parseModel();
        }

        public function parseOrgStock($sourceId): ?OrgStock
        {
            $this->lookedUpOrgStock = true;

            return null;
        }
    };

    $row       = auroraMovementRow($type, $section);
    $row->Note = $note;
    $fetcher->feed($row);

    expect($fetcher->lookedUpOrgStock)->toBe($reachesParsing);
})->with([
    'sale (picking is done in aiku)'         => ['Sale', '', false],
    'in (booking in is done in aiku)'        => ['In', '', false],
    'in from a production delivery'          => ['In', '', true, 'production'],
    'restock (returns are sowed in aiku)'    => ['Restock', '', false],
    'production consumption'                 => ['Production', 'Out', true],
    'production return of consumed stock'    => ['Production', 'In', true],
]);

it('keeps aurora away from the placement of a delivery that is booked in from aiku', function (string $parentType, bool $placesInAiku) {
    $organisation = $this->organisation;
    $organisation->update(['is_aiku_stock_control' => true]);

    $stockDelivery = new StockDelivery(['parent_type' => $parentType]);
    $stockDelivery->setRelation('organisation', $organisation);

    expect($stockDelivery->placesInAiku())->toBe($placesInAiku);
})->with([
    'supplier delivery' => ['OrgSupplier', true],
    'agent delivery'    => ['OrgAgent', true],
    'production order'  => ['Production', false],
]);

it('strips only what aurora knows about placement from a fetched delivery or item', function () {
    expect(StockDelivery::withoutAuroraPlacement([
        'reference'             => 'kept',
        'unit_quantity_checked' => 10,
        'unit_quantity_placed'  => 10,
        'placed_at'             => '2026-01-01',
        'state'                 => StockDeliveryStateEnum::CHECKED,
    ]))->toBe(['reference' => 'kept', 'unit_quantity_checked' => 10])
        ->and(StockDelivery::withoutAuroraPlacement(['state' => StockDeliveryStateEnum::PLACED]))->toBe([])
        ->and(StockDelivery::withoutAuroraPlacement(['state' => StockDeliveryStateEnum::RECEIVED]))->toBe(['state' => StockDeliveryStateEnum::RECEIVED])
        ->and(StockDelivery::withoutAuroraPlacement(['state' => StockDeliveryStateEnum::CANCELLED]))->toBe(['state' => StockDeliveryStateEnum::CANCELLED]);
});
