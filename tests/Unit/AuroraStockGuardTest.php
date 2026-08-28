<?php

use App\Actions\Transfers\Aurora\WithFetchStock;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;

/**
 * This one genuinely needs rows: processOrgStock looks the org stock up before deciding.
 * Everything runs inside a transaction that is rolled back, so the suite sees no trace of
 * it — createOrganisation() is deliberately not used, because building the organisation the
 * feature tests share from a unit test leaves InventoryTest unable to run.
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

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('never lets aurora update an existing org stock, whichever organisation it belongs to', function (bool $aikuStockControl) {
    $unique = uniqid();

    // createOrganisation() is safe here only because the surrounding transaction is
    // rolled back: creating the shared organisation from a unit test and leaving it behind
    // is what breaks InventoryTest.
    $organisation = createOrganisation();
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
