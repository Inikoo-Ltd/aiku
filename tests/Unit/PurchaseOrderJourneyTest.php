<?php

use App\Actions\SupplyChain\UI\GetPurchaseOrderJourney;
use Illuminate\Support\Carbon;

function journeyFor(array $facts, string $today = '2026-09-24'): array
{
    return GetPurchaseOrderJourney::run(array_merge([
        'journey'        => 'agent',
        'state'          => 'submitted',
        'delivery_state' => 'in_process',
        'created_at'     => '2026-09-01',
        'submitted_at'   => '2026-09-02',
    ], $facts), Carbon::parse($today));
}

function segmentOf(array $journey, string $key): ?array
{
    return collect($journey['segments'])->firstWhere('key', $key);
}

it('runs an agent order nobody tracks on the dates the system records, with the agent stages only planned', function () {
    $journey = journeyFor(['delivery_time' => 90]);

    expect($journey['current_stage'])->toBe('dispatched')
        ->and($journey['status'])->toBe('on_track')
        ->and(segmentOf($journey, 'production')['state'])->toBe('untracked')
        ->and(segmentOf($journey, 'clean_handover')['state'])->toBe('untracked')
        ->and(segmentOf($journey, 'spec_sample'))->toBeNull()
        ->and(Carbon::parse('2026-09-02')->diffInDays(Carbon::parse(segmentOf($journey, 'in_transit')['planned_at'])))->toBe(90.0);
});

it('holds a tracked agent order at the first stage nobody marked and counts the days it is late', function () {
    $journey = journeyFor([
        'deposit_paid_at' => '2026-07-01',
        'stage_days'      => ['production' => 30],
        'created_at'      => '2026-06-20',
        'submitted_at'    => '2026-06-25',
    ]);

    expect($journey['current_stage'])->toBe('production')
        ->and($journey['status'])->toBe('overdue')
        ->and($journey['days_overdue'])->toBe(55)
        ->and(segmentOf($journey, 'production')['planned_at'])->toBe('2026-07-31');
});

it('marks earlier stages as done without a date once a later stage happened', function () {
    $journey = journeyFor(['delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-20', 'deposit_paid_at' => '2026-09-05']);

    expect(segmentOf($journey, 'production')['state'])->toBe('skipped')
        ->and(segmentOf($journey, 'qc')['state'])->toBe('skipped')
        ->and($journey['current_stage'])->toBe('in_transit');
});

it('turns a draft that was never sent red at PO created', function () {
    $journey = journeyFor(['journey' => 'supplier', 'state' => 'in_process', 'submitted_at' => null, 'created_at' => '2026-09-01']);

    expect($journey['current_stage'])->toBe('po_created')
        ->and($journey['status'])->toBe('overdue')
        ->and($journey['days_overdue'])->toBe(20)
        ->and(segmentOf($journey, 'deposit_paid'))->toBeNull()
        ->and(segmentOf($journey, 'production'))->toBeNull();
});

it('adds spec and sample only to new product orders, never to inter-company ones', function () {
    expect(segmentOf(journeyFor(['is_npo' => true]), 'spec_sample'))->not->toBeNull()
        ->and(segmentOf(journeyFor(['is_npo' => false]), 'spec_sample'))->toBeNull()
        ->and(segmentOf(journeyFor(['is_npo' => true, 'journey' => 'partner']), 'spec_sample'))->toBeNull();
});

it('completes once every product from the order is online', function () {
    $facts = [
        'journey'           => 'partner',
        'state'             => 'settled',
        'delivery_state'    => 'placed',
        'dispatched_at'     => '2026-09-10',
        'received_at'       => '2026-09-12',
        'placed_at'         => '2026-09-13',
        'sellable_products' => 2,
        'online_at'         => '2026-09-15 10:00:00',
    ];

    $waiting  = journeyFor([...$facts, 'online_products' => 1]);
    $complete = journeyFor([...$facts, 'online_products' => 2]);

    expect($waiting['current_stage'])->toBe('products_online')
        ->and($complete['status'])->toBe('completed')
        ->and($complete['eta'])->toBe('2026-09-15');
});

it('warns three days before a target', function () {
    $journey = journeyFor(['journey' => 'supplier', 'estimated_received_at' => '2026-09-26', 'delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-20']);

    expect($journey['current_stage'])->toBe('in_transit')
        ->and($journey['status'])->toBe('at_risk');
});

it('keeps the agreed plan on later stages and moves only the estimate when a stage runs late', function () {
    $journey = journeyFor([
        'journey'       => 'supplier',
        'created_at'    => '2026-06-01',
        'submitted_at'  => '2026-06-01',
        'delivery_time' => 21,
    ]);

    $inTransit = segmentOf($journey, 'in_transit');

    expect($journey['current_stage'])->toBe('dispatched')
        ->and($journey['status'])->toBe('overdue')
        ->and($inTransit['planned_at'])->toBe('2026-06-22')
        ->and($inTransit['behind_plan'])->toBeTrue()
        ->and($inTransit['forecast_at'] > '2026-09-24')->toBeTrue()
        ->and($journey['eta'] > '2026-09-24')->toBeTrue();
});
