<?php

use App\Actions\Transfers\Aurora\FetchAuroraOrders;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;

/**
 * The guard returns 0 without touching Aurora. Letting a fetch through means reaching
 * getOrganisationSource, which cannot connect from a test and makes the parent return 1 —
 * so the two outcomes tell the cases apart without a legacy database.
 */
function fetchOrdersCommand(?string $sourceId, bool $force = false): Command
{
    $command = Mockery::mock(Command::class)->shouldIgnoreMissing();
    $command->shouldReceive('hasOption')->with('source_id')->andReturn(true);
    $command->shouldReceive('option')->with('source_id')->andReturn($sourceId);
    $command->shouldReceive('hasOption')->with('force')->andReturn(true);
    $command->shouldReceive('option')->with('force')->andReturn($force);
    $command->shouldReceive('getName')->andReturn('fetch:orders');

    return $command;
}

function runOrdersFetchFor(Organisation $organisation, ?string $sourceId): int
{
    return (new FetchAuroraOrders())->processOrganisation(fetchOrdersCommand($sourceId), $organisation);
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

it('only overwrites an aiku record when force is asked for out loud', function (?string $sourceId, bool $force, bool $expected) {
    $organisation = createOrganisation();
    $organisation->update(['slug' => 'aroma']);

    $action  = new FetchAuroraOrders();
    $command = fetchOrdersCommand($sourceId, $force);

    $forced = new ReflectionProperty($action, 'forcedSourceFetch');
    $forced->setAccessible(true);

    try {
        $action->processOrganisation($command, $organisation);
    } catch (Throwable) {
        // reaching Aurora is not the point, the flag being set before it is
    }

    expect($forced->getValue($action))->toBe($expected);
})->with([
    'named record alone does not overwrite' => ['2788500', false, false],
    'named record with --force overwrites'  => ['2788500', true, true],
    'wholesale run overwrites nothing'      => [null, false, false],
]);
