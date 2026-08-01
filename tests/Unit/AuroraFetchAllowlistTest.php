<?php

use App\Actions\Transfers\Aurora\FetchAuroraCustomers;
use App\Actions\Transfers\Aurora\FetchAuroraDeliveryNotes;
use App\Actions\Transfers\Aurora\FetchAuroraPurchaseOrders;
use App\Actions\Transfers\Aurora\FetchAuroraStockLocations;
use App\Actions\Transfers\Aurora\FetchAuroraSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraTimesheets;
use App\Models\SysAdmin\Organisation;

function auroraStillFeeds(string $fetcherClass, Organisation $organisation): bool
{
    $method = new ReflectionMethod($fetcherClass, 'auroraStillFeeds');
    $method->setAccessible(true);

    return $method->invoke(new $fetcherClass(), $organisation);
}

it('only lets a departed organisation keep the fetchers it has no aiku replacement for', function (string $fetcher, bool $expected) {
    $organisation = createOrganisation();
    config(['aurora.following_organisations' => ['aroma']]);
    $organisation->update(['slug' => 'aw']);

    expect(auroraStillFeeds($fetcher, $organisation))->toBe($expected);
})->with([
    'purchase orders still come from aurora' => [FetchAuroraPurchaseOrders::class, true],
    'suppliers still come from aurora'       => [FetchAuroraSuppliers::class, true],
    'timesheets are the clocking machine'    => [FetchAuroraTimesheets::class, true],
    'customers are aiku owned now'           => [FetchAuroraCustomers::class, false],
    'stock locations are aroma only'         => [FetchAuroraStockLocations::class, false],
    'delivery notes are aiku owned now'      => [FetchAuroraDeliveryNotes::class, false],
]);

it('leaves an organisation that still follows aurora untouched', function () {
    $organisation = createOrganisation();
    config(['aurora.following_organisations' => ['aroma']]);
    $organisation->update(['slug' => 'aroma']);

    expect(auroraStillFeeds(FetchAuroraCustomers::class, $organisation))->toBeTrue()
        ->and(auroraStillFeeds(FetchAuroraStockLocations::class, $organisation))->toBeTrue();
});
