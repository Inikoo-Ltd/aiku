<?php

use App\Actions\Transfers\Aurora\FetchAuroraOrders;
use Illuminate\Console\Command;

/**
 * These tests share one organisation (createOrganisation returns the first one), so each
 * sets every field it depends on. The guard's decision is read from what it printed
 * rather than from a return code, which otherwise depends on whether Aurora happens to be
 * reachable.
 */
function fetchOrdersCommand(?string $sourceId, bool $force, array &$lines): Command
{
    $command = Mockery::mock(Command::class)->shouldIgnoreMissing();
    $command->shouldReceive('hasOption')->with('source_id')->andReturn(true);
    $command->shouldReceive('option')->with('source_id')->andReturn($sourceId);
    $command->shouldReceive('hasOption')->with('force')->andReturn(true);
    $command->shouldReceive('option')->with('force')->andReturn($force);
    $command->shouldReceive('getName')->andReturn('fetch:orders');
    $command->shouldReceive('line')->andReturnUsing(function ($message) use (&$lines) {
        $lines[] = $message;
    });

    return $command;
}

function guardRefused(string $slug, ?string $sourceId, bool $force): bool
{
    $organisation = createOrganisation();
    $organisation->update(['slug' => $slug, 'source' => ['type' => 'Aurora']]);

    $lines = [];

    try {
        (new FetchAuroraOrders())->processOrganisation(fetchOrdersCommand($sourceId, $force, $lines), $organisation);
    } catch (Throwable) {
        // Aurora is unreachable from a test; the guard runs before any of that
    }

    return (bool)preg_grep('/no longer follows Aurora/', $lines);
}

beforeEach(function () {
    config(['aurora.following_organisations' => ['aroma']]);
});

it('refuses a denied fetcher unless both -s and --force are given', function (?string $sourceId, bool $force, bool $refused) {
    expect(guardRefused('aw', $sourceId, $force))->toBe($refused);
})->with([
    'nothing given, refused'       => [null, false, true],
    '-s alone, still refused'      => ['2788500', false, true],
    '--force alone, still refused' => [null, true, true],
    '-s with --force, allowed'     => ['2788500', true, false],
]);

it('never refuses an organisation that still follows aurora', function () {
    expect(guardRefused('aroma', null, false))->toBeFalse();
});

it('hands the forced flag to the source object the parsers read', function (?string $sourceId, bool $force, bool $expected) {
    $organisation = createOrganisation();
    $organisation->update(['slug' => 'aroma', 'source' => ['type' => 'Aurora']]);

    $action = new FetchAuroraOrders();
    $lines  = [];

    try {
        $action->processOrganisation(fetchOrdersCommand($sourceId, $force, $lines), $organisation);
    } catch (Throwable) {
        // connecting to Aurora is not the point; what the source ends up carrying is
    }

    // Read exactly the way FetchAuroraOrder, FetchAuroraInvoice and FetchAuroraDeliveryNote
    // read it. Fails if the getOrganisationSource override that carries the flag is dropped.
    expect($action->getOrganisationSource($organisation)->isForcedFetch())->toBe($expected);
})->with([
    'forced override reaches the parsers' => ['2788500', true, true],
    '-s alone does not'                   => ['2788500', false, false],
    '--force alone does not'              => [null, true, false],
]);
