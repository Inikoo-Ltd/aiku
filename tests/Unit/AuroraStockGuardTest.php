<?php

use App\Actions\Transfers\Aurora\WithFetchStock;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Transfers\SourceOrganisationService;

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

it('never lets aurora update an existing org stock, whichever organisation it belongs to', function (bool $aikuStockControl) {
    $organisation = createOrganisation();
    $organisation->update(['is_aiku_stock_control' => $aikuStockControl]);
    $unique   = uniqid();
    $sourceId = 'aurora:guard-'.$unique;

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
        'source_id'       => $sourceId,
        'name'            => 'edited in aiku',
    ]);

    $source = Mockery::mock(SourceOrganisationService::class);
    $source->shouldReceive('getOrganisation')->andReturn($organisation);

    auroraStockGuardHarness()->run($source, $stock, [
        'stock'     => ['source_id' => $sourceId, 'code' => $orgStock->code],
        'org_stock' => ['name' => 'overwritten by aurora', 'source_id' => $sourceId],
    ]);

    expect($orgStock->refresh()->name)->toBe('edited in aiku');
})->with([
    'organisation already runs stock control in aiku' => [true],
    'organisation still follows aurora, eg aroma'     => [false],
]);
