<?php

use App\Actions\Transfers\Aurora\WithFetchStock;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Transfers\SourceOrganisationService;

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

it('lets only production movements through from aurora when the organisation runs stock control in aiku', function (string $type, string $section, bool $reachesParsing) {
    $organisation = $this->organisation;
    $organisation->update(['is_aiku_stock_control' => true]);

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

    $fetcher->feed(auroraMovementRow($type, $section));

    expect($fetcher->lookedUpOrgStock)->toBe($reachesParsing);
})->with([
    'sale (picking is done in aiku)'         => ['Sale', '', false],
    'in (booking in is done in aiku)'        => ['In', '', false],
    'restock (returns are sowed in aiku)'    => ['Restock', '', false],
    'production consumption'                 => ['Production', 'Out', true],
    'production return of consumed stock'    => ['Production', 'In', true],
]);
