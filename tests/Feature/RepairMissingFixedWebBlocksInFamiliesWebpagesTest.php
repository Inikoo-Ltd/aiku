<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);
});

function familyWebpageBlockCodes(int $webpageId): array
{
    return DB::table('model_has_web_blocks')
        ->join('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
        ->join('web_block_types', 'web_block_types.id', '=', 'web_blocks.web_block_type_id')
        ->where('model_has_web_blocks.model_type', 'Webpage')
        ->where('model_has_web_blocks.model_id', $webpageId)
        ->pluck('web_block_types.code')
        ->all();
}

test('repairs a family webpage using the website family description template', function (array $familyDescriptionCodes) {
    [, $product] = createProduct($this->shop);
    $webpage     = StoreProductCategoryWebpage::make()->action($product->family);

    $snapshot = StoreWebsiteSnapshot::make()->action($this->website, [
        'scope'  => SnapshotScopeEnum::FAMILY_DESCRIPTION,
        'layout' => array_fill_keys($familyDescriptionCodes, ['data' => ['fieldValue' => []]]),
    ]);
    $this->website->update(['live_family_description_snapshot_id' => $snapshot->id]);

    artisan('repair:missing_fixed_web_blocks_in_families_webpages', ['--webpage_id' => $webpage->id])
        ->assertSuccessful();

    $blockCodes = familyWebpageBlockCodes($webpage->id);

    expect($blockCodes)->toContain(...$familyDescriptionCodes)
        ->and(array_count_values($blockCodes)[$familyDescriptionCodes[0]])->toBe(1)
        ->and($blockCodes)->not->toContain('department-description-1');
})->with([
    'family-1'                    => [['family-1']],
    'family-2 with extra content' => [['family-2', 'family-2-extra-description']],
]);
