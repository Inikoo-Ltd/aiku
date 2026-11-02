<?php

use App\Actions\Transfers\Aurora\FetchAuroraOrgStockMovements;
use App\Actions\Transfers\Aurora\FetchAuroraPurchaseOrgStockMovements;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Support\Facades\Cache;

/**
 * HELP-4434: the fetch stack job and the fetch:stock_movements cron fetched the same Aurora movement at
 * once and both stored it. Both fetchers must look up and store a movement while holding the same lock.
 * Nothing here touches the database: the source returns no data once the lock has been checked.
 */
dataset('org stock movement fetchers', [
    'stock movements'          => [FetchAuroraOrgStockMovements::class],
    'purchase stock movements' => [FetchAuroraPurchaseOrgStockMovements::class],
]);

test('an Aurora movement is fetched while holding its per source lock', function (string $fetcher) {
    $organisationSource               = Mockery::mock(AuroraOrganisationService::class);
    $organisationSource->organisation = (new Organisation())->forceFill(['id' => 987654]);

    $lockWasHeld = null;
    $organisationSource->shouldReceive('fetchOrgStockMovement')->with(4434)->once()->andReturnUsing(function () use (&$lockWasHeld) {
        $lockWasHeld = !Cache::lock('fetch-org-stock-movement:987654:4434')->get();

        return null;
    });

    expect((new $fetcher())->handle($organisationSource, 4434))->toBeNull()
        ->and($lockWasHeld)->toBeTrue()
        ->and(Cache::lock('fetch-org-stock-movement:987654:4434')->get())->toBeTrue();

    Cache::lock('fetch-org-stock-movement:987654:4434')->forceRelease();
})->with('org stock movement fetchers');
