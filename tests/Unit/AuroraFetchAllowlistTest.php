<?php

use App\Actions\Transfers\Aurora\FetchAuroraArtefacts;
use App\Actions\Transfers\Aurora\FetchAuroraCustomers;
use App\Actions\Transfers\Aurora\FetchAuroraDeletedSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraDeliveryNotes;
use App\Actions\Transfers\Aurora\FetchAuroraHistoricSupplierProducts;
use App\Actions\Transfers\Aurora\FetchAuroraLocations;
use App\Actions\Transfers\Aurora\FetchAuroraStockFamilies;
use App\Actions\Transfers\Aurora\FetchAuroraWarehouses;
use App\Actions\Transfers\Aurora\FetchAuroraJobOrders;
use App\Actions\Transfers\Aurora\FetchAuroraRawMaterials;
use App\Actions\Transfers\Aurora\FetchAuroraPurchaseOrders;
use App\Actions\Transfers\Aurora\FetchAuroraStockLocations;
use App\Actions\Transfers\Aurora\FetchAuroraSuppliers;
use App\Actions\Transfers\Aurora\FetchAuroraTimesheets;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraCatalogueGuard;
use App\Transfers\AuroraOrganisationService;

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
    'job orders still come from aurora'      => [FetchAuroraJobOrders::class, true],
    'artefacts still come from aurora'       => [FetchAuroraArtefacts::class, true],
    'raw materials still come from aurora'   => [FetchAuroraRawMaterials::class, true],
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

function auroraSourceFor(string $slug, bool $forced = false): AuroraOrganisationService
{
    $source = new AuroraOrganisationService();
    $source->organisation = organisationNamed($slug);
    $source->setForcedFetch($forced);

    return $source;
}

it('refuses a transitive fetch-on-miss for a departed organisation', function (string $fetcher, bool $expected) {
    expect(auroraSourceFor('aw')->allowsFetchOnMiss($fetcher))->toBe($expected);
})->with([
    'locations no longer feed from aurora'  => [FetchAuroraLocations::class, false],
    'warehouses no longer feed from aurora' => [FetchAuroraWarehouses::class, false],
    'deleted suppliers stay lookup only'    => [FetchAuroraDeletedSuppliers::class, false],
    'customers stay lookup only'            => [FetchAuroraCustomers::class, false],
    'suppliers may still create on miss'    => [FetchAuroraSuppliers::class, true],
    'stock families follow allowed stocks'  => [FetchAuroraStockFamilies::class, true],
    'historic supplier products follow allowed supplier products' => [FetchAuroraHistoricSupplierProducts::class, true],
]);

it('lets a following organisation and a forced fetch through the on-miss gate', function () {
    expect(auroraSourceFor('aroma')->allowsFetchOnMiss(FetchAuroraCustomers::class))->toBeTrue()
        ->and(auroraSourceFor('aw', forced: true)->allowsFetchOnMiss(FetchAuroraCustomers::class))->toBeTrue();
});

it('returns null from run instead of fetching when the gate refuses', function () {
    expect(FetchAuroraLocations::run(auroraSourceFor('aw'), 1))->toBeNull();
});

it('freezes every catalogue-tier model against aurora updates while a fetch runs', function (string $modelClass) {
    $model = (new $modelClass())->forceFill(['id' => 1]);
    $model->exists = true;

    expect(AuroraCatalogueGuard::blocksUpdate($model))->toBeFalse()
        ->and(AuroraCatalogueGuard::during(fn () => AuroraCatalogueGuard::blocksUpdate($model)))->toBeTrue();
})->with([
    'trade units'               => [\App\Models\Goods\TradeUnit::class],
    'products'                  => [\App\Models\Catalogue\Product::class],
    'assets'                    => [\App\Models\Catalogue\Asset::class],
    'historic assets'           => [\App\Models\Catalogue\HistoricAsset::class],
    'product categories'        => [\App\Models\Catalogue\ProductCategory::class],
    'collections'               => [\App\Models\Catalogue\Collection::class],
    'stocks'                    => [\App\Models\Goods\Stock::class],
    'org stocks'                => [\App\Models\Inventory\OrgStock::class],
    'stock families'            => [\App\Models\Goods\StockFamily::class],
    'master assets'             => [\App\Models\Masters\MasterAsset::class],
    'master product categories' => [\App\Models\Masters\MasterProductCategory::class],
    'master shops'              => [\App\Models\Masters\MasterShop::class],
]);

it('lets a row the same fetch just created finish being assembled', function () {
    $product = (new \App\Models\Catalogue\Product())->forceFill(['id' => 1]);
    $product->exists = true;
    $product->wasRecentlyCreated = true;

    expect(AuroraCatalogueGuard::during(fn () => AuroraCatalogueGuard::blocksUpdate($product)))->toBeFalse();
});

it('does not freeze models outside the catalogue tier', function () {
    $organisation = organisationNamed('aw');
    $organisation->exists = true;

    expect(AuroraCatalogueGuard::during(fn () => AuroraCatalogueGuard::blocksUpdate($organisation)))->toBeFalse();
});

it('makes the shared update chokepoint a no-op on a frozen model', function () {
    $updater = new class () {
        use \App\Actions\Traits\WithActionUpdate;

        public function attempt($model, array $modelData)
        {
            return $this->update($model, $modelData);
        }
    };

    $tradeUnit = (new \App\Models\Goods\TradeUnit())->forceFill(['id' => 1, 'name' => 'kept']);
    $tradeUnit->exists = true;
    $tradeUnit->syncOriginal();

    $result = AuroraCatalogueGuard::during(fn () => $updater->attempt($tradeUnit, ['name' => 'from aurora']));

    expect($result->name)->toBe('kept')
        ->and($result->isDirty())->toBeFalse();
});

it('drops the guard even when the fetch throws', function () {
    try {
        AuroraCatalogueGuard::during(fn () => throw new RuntimeException('fetch blew up'));
    } catch (RuntimeException) {
    }

    expect(AuroraCatalogueGuard::active())->toBeFalse();
});
