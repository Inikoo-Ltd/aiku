<?php

use App\Actions\Transfers\Aurora\FetchAuroraOrders;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;

/**
 * The guard returns 0 without touching Aurora. Letting a fetch through means reaching
 * getOrganisationSource, which cannot connect from a test and makes the parent return 1 —
 * so the two outcomes tell the cases apart without a legacy database.
 */
function runOrdersFetchFor(Organisation $organisation, ?string $sourceId): int
{
    $command = Mockery::mock(Command::class)->shouldIgnoreMissing();
    $command->shouldReceive('hasOption')->with('source_id')->andReturn(true);
    $command->shouldReceive('option')->with('source_id')->andReturn($sourceId);
    $command->shouldReceive('getName')->andReturn('fetch:orders');

    return (new FetchAuroraOrders())->processOrganisation($command, $organisation);
}

beforeEach(function () {
    config(['aurora.following_organisations' => ['aroma']]);
});

it('still fetches one named order by hand after the organisation left aurora', function () {
    $organisation = createOrganisation();
    $organisation->update(['slug' => 'aw']);

    expect(runOrdersFetchFor($organisation, '2788500'))->not->toBe(0);
});

it('refuses a wholesale order fetch for an organisation that left aurora', function () {
    $organisation = createOrganisation();
    $organisation->update(['slug' => 'aw']);

    expect(runOrdersFetchFor($organisation, null))->toBe(0);
});
