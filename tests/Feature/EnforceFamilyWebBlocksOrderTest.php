<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Web\Webpage;

use function Pest\Laravel\artisan;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);
});

function orderedWebBlockCodes(Webpage $webpage): array
{
    return $webpage->webBlocks()->with('webBlockType')->get()->pluck('webBlockType.code')->all();
}

test('family template blocks stay on top in a fixed order after a reorder', function (string $familyCode, string $extraDescriptionCode) {
    [, $product] = createProduct($this->shop);
    $webpage     = StoreProductCategoryWebpage::make()->action($product->family);

    $snapshot = StoreWebsiteSnapshot::make()->action($this->website, [
        'scope'  => SnapshotScopeEnum::FAMILY_DESCRIPTION,
        'layout' => array_fill_keys([$familyCode, $extraDescriptionCode], ['data' => ['fieldValue' => []]]),
    ]);
    $this->website->update(['live_family_description_snapshot_id' => $snapshot->id]);

    artisan('repair:missing_fixed_web_blocks_in_families_webpages', ['--webpage_id' => $webpage->id])
        ->assertSuccessful();

    $reversedPositions = $webpage->webBlocks()->get()->reverse()->values()
        ->mapWithKeys(fn ($webBlock, $index) => [$webBlock->id => ['position' => $index]])
        ->all();

    ReorderWebBlocks::make()->action($webpage, ['positions' => $reversedPositions]);

    $codes = orderedWebBlockCodes($webpage->refresh());

    expect($codes[0])->toBe($familyCode)
        ->and($codes[1])->toStartWith('products-')
        ->and($codes[2])->toBe($extraDescriptionCode);
})->with([
    'family-2' => ['family-2', 'family-2-extra-description'],
    'family-3' => ['family-3', 'family-3-extra-description'],
]);
