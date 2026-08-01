<?php

use App\Actions\Transfers\Aurora\FetchAuroraCustomers;
use App\Actions\Transfers\Aurora\FetchAuroraDeliveryNotes;
use App\Actions\Transfers\Aurora\FetchAuroraPurchaseOrders;
use App\Actions\Transfers\Aurora\FetchAuroraStockLocations;
use App\Actions\Transfers\Aurora\FetchAuroraSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraTimesheets;
use App\Models\SysAdmin\Organisation;

/**
 * Nothing here goes near the database. createOrganisation() builds the one organisation the
 * feature tests share, and creating it from a unit test leaves the suite in a state
 * InventoryTest cannot work from. The guard only reads a slug, so an unsaved model is all
 * it needs.
 */
function auroraStillFeeds(string $fetcherClass, Organisation $organisation): bool
{
    $method = new ReflectionMethod($fetcherClass, 'auroraStillFeeds');
    $method->setAccessible(true);

    return $method->invoke(new $fetcherClass(), $organisation);
}

function organisationNamed(string $slug): Organisation
{
    return (new Organisation())->forceFill(['slug' => $slug]);
}

beforeEach(function () {
    config(['aurora.following_organisations' => ['aroma']]);
});

it('only lets a departed organisation keep the fetchers it has no aiku replacement for', function (string $fetcher, bool $expected) {
    expect(auroraStillFeeds($fetcher, organisationNamed('aw')))->toBe($expected);
})->with([
    'purchase orders still come from aurora' => [FetchAuroraPurchaseOrders::class, true],
    'suppliers still come from aurora'       => [FetchAuroraSuppliers::class, true],
    'timesheets are the clocking machine'    => [FetchAuroraTimesheets::class, true],
    'customers are aiku owned now'           => [FetchAuroraCustomers::class, false],
    'stock locations are aroma only'         => [FetchAuroraStockLocations::class, false],
    'delivery notes are aiku owned now'      => [FetchAuroraDeliveryNotes::class, false],
]);

it('leaves an organisation that still follows aurora untouched', function () {
    expect(auroraStillFeeds(FetchAuroraCustomers::class, organisationNamed('aroma')))->toBeTrue()
        ->and(auroraStillFeeds(FetchAuroraStockLocations::class, organisationNamed('aroma')))->toBeTrue();
});
