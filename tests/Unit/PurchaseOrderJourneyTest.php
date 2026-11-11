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

    expect($journey['current_stage'])->toBe('handed_over')
        ->and($journey['status'])->toBe('on_track')
        ->and(segmentOf($journey, 'in_production')['state'])->toBe('untracked')
        ->and(segmentOf($journey, 'ready')['state'])->toBe('untracked')
        ->and(Carbon::parse('2026-09-02')->diffInDays(Carbon::parse(segmentOf($journey, 'received')['planned_at'])))->toBe(90.0);
});

it('holds a tracked agent order at the first stage nobody marked and counts the days it is late', function () {
    $journey = journeyFor([
        'deposit_paid_at' => '2026-07-01',
        'stage_days'      => ['in_production' => 30],
        'created_at'      => '2026-06-20',
        'submitted_at'    => '2026-06-25',
    ]);

    expect($journey['current_stage'])->toBe('in_production')
        ->and($journey['status'])->toBe('overdue')
        ->and($journey['days_overdue'])->toBe(55)
        ->and(segmentOf($journey, 'in_production')['planned_at'])->toBe('2026-07-31');
});

it('marks earlier stages as done without a date once a later stage happened', function () {
    $journey = journeyFor(['delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-20', 'deposit_paid_at' => '2026-09-05']);

    expect(segmentOf($journey, 'in_production')['state'])->toBe('skipped')
        ->and(segmentOf($journey, 'ready')['state'])->toBe('skipped')
        ->and($journey['current_stage'])->toBe('arrived');
});

it('turns a draft that was never sent red at PO submitted', function () {
    $journey = journeyFor(['journey' => 'supplier', 'state' => 'in_process', 'submitted_at' => null, 'created_at' => '2026-09-01']);

    expect($journey['current_stage'])->toBe('submitted')
        ->and($journey['status'])->toBe('overdue')
        ->and($journey['days_overdue'])->toBe(20)
        ->and(segmentOf($journey, 'deposit_paid'))->toBeNull()
        ->and(segmentOf($journey, 'in_production'))->toBeNull();
});

it('shows production for agent orders and for suppliers that take production days', function () {
    expect(segmentOf(journeyFor([]), 'in_production'))->not->toBeNull()
        ->and(segmentOf(journeyFor(['journey' => 'supplier']), 'in_production'))->toBeNull()
        ->and(segmentOf(journeyFor(['journey' => 'supplier', 'production_waiting_time' => 10]), 'in_production'))->not->toBeNull();
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
    $journey = journeyFor(['journey' => 'supplier', 'estimated_received_at' => '2026-09-28', 'delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-20']);

    expect($journey['current_stage'])->toBe('arrived')
        ->and($journey['status'])->toBe('at_risk');
});

it('keeps the agreed plan on later stages and moves only the estimate when a stage runs late', function () {
    $journey = journeyFor([
        'journey'       => 'supplier',
        'created_at'    => '2026-06-01',
        'submitted_at'  => '2026-06-01',
        'delivery_time' => 21,
    ]);

    $received = segmentOf($journey, 'received');

    expect($journey['current_stage'])->toBe('handed_over')
        ->and($journey['status'])->toBe('overdue')
        ->and($received['planned_at'])->toBe('2026-06-22')
        ->and($received['behind_plan'])->toBeTrue()
        ->and($received['forecast_at'] > '2026-09-24')->toBeTrue()
        ->and($journey['eta'] > '2026-09-24')->toBeTrue();
});

it('does not count a draft as sent even when it carries a submitted date', function () {
    $journey = journeyFor(['journey' => 'partner', 'state' => 'in_process', 'submitted_at' => '2026-09-02', 'created_at' => '2026-09-01']);

    expect(segmentOf($journey, 'submitted')['state'])->toBe('overdue')
        ->and(segmentOf($journey, 'submitted')['done_at'])->toBeNull();
});

it('targets QC on the approved ready date and shipping, after the clean handover, seven days after it', function () {
    $journey = journeyFor(['deposit_paid_at' => '2026-09-05', 'approved_ready_at' => '2026-10-20']);

    expect(segmentOf($journey, 'ready')['planned_at'])->toBe('2026-10-20')
        ->and(segmentOf($journey, 'handed_over')['planned_at'])->toBe('2026-10-27');
});

it('works the shipping target back from a recorded arrival date', function () {
    $journey = journeyFor([
        'journey'               => 'partner',
        'created_at'            => '2026-08-01',
        'submitted_at'          => '2026-08-01',
        'estimated_received_at' => '2026-09-30',
    ]);

    expect(segmentOf($journey, 'handed_over')['planned_at'])->toBe('2026-09-26')
        ->and($journey['status'])->toBe('at_risk');
});

it('counts the goods as arrived once every delivery reached the port', function () {
    $journey = journeyFor(['journey' => 'supplier', 'delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-10', 'arrived' => true, 'arrived_at' => '2026-09-20']);

    expect(segmentOf($journey, 'arrived')['state'])->toBe('done')
        ->and(segmentOf($journey, 'arrived')['done_at'])->toBe('2026-09-20')
        ->and($journey['current_stage'])->toBe('received');
});

it('leaves the online date empty when the date the goods were placed is unknown', function () {
    $journey = journeyFor([
        'journey'           => 'supplier',
        'state'             => 'settled',
        'delivery_state'    => 'placed',
        'sellable_products' => 1,
        'online_products'   => 1,
        'online_at'         => '2019-11-07',
    ]);

    expect(segmentOf($journey, 'products_online')['state'])->toBe('done')
        ->and(segmentOf($journey, 'products_online')['done_at'])->toBeNull();
});

it('lists the stages in the board order with Clean Hand Over before Homeward Bound and no shipped stage', function () {
    $keys = collect(journeyFor(['sellable_products' => 1])['segments'])->pluck('key')->all();

    expect($keys)->toBe(['submitted', 'deposit_paid', 'in_production', 'ready', 'handed_over', 'arrived', 'received', 'products_online'])
        ->and(App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum::values())->toBe($keys)
        ->and(App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum::labels())->toMatchArray([
            'submitted'       => 'PO Submitted',
            'handed_over'     => 'Clean Hand Over',
            'arrived'         => 'Homeward Bound',
            'products_online' => 'Mission Complete',
        ]);
});

it('completes Clean Hand Over when the agent marks it', function () {
    $journey = journeyFor(['cho_mode' => 'forwarder', 'handed_over_at' => '2026-09-15']);

    expect(segmentOf($journey, 'handed_over')['state'])->toBe('done')
        ->and(segmentOf($journey, 'handed_over')['done_at'])->toBe('2026-09-15')
        ->and($journey['current_stage'])->toBe('arrived');
});

it('completes Clean Hand Over for an agent sending on the next container once every item is in a container', function () {
    $journey = journeyFor(['cho_mode' => 'next_container', 'on_containers' => true, 'on_containers_at' => '2026-09-10']);

    expect(segmentOf($journey, 'handed_over')['state'])->toBe('done')
        ->and(segmentOf($journey, 'handed_over')['done_at'])->toBe('2026-09-10');
});

it('does not complete Clean Hand Over for an agent sending to a forwarder just because the items are in a container', function () {
    $journey = journeyFor(['cho_mode' => 'forwarder', 'on_containers' => true, 'on_containers_at' => '2026-09-10']);

    expect(segmentOf($journey, 'handed_over')['state'])->not->toBe('done')
        ->and($journey['current_stage'])->toBe('handed_over');
});

it('completes Clean Hand Over on dispatch in both modes and for suppliers and partners', function (array $facts) {
    $journey = journeyFor([...$facts, 'delivery_state' => 'dispatched', 'dispatched_at' => '2026-09-20']);

    expect(segmentOf($journey, 'handed_over')['state'])->toBe('done')
        ->and(segmentOf($journey, 'handed_over')['done_at'])->toBe('2026-09-20');
})->with([
    'next container' => [['cho_mode' => 'next_container']],
    'forwarder'      => [['cho_mode' => 'forwarder']],
    'supplier'       => [['journey' => 'supplier']],
    'partner'        => [['journey' => 'partner']],
]);

it('words a stage as the agent set it and falls back to the standard wording', function () {
    $journey = journeyFor(['stage_texts' => ['ready' => ['label' => 'Final inspection', 'description' => null]]]);

    expect(segmentOf($journey, 'ready')['label'])->toBe('Final inspection')
        ->and(segmentOf($journey, 'ready')['description'])->toBe('Final checks')
        ->and(segmentOf($journey, 'handed_over')['label'])->toBe('Clean Hand Over');
});

it('lists the agent ready checks with what is done', function () {
    $journey = journeyFor([
        'ready_checks'    => ['balance', 'packing', 'qc'],
        'balance_paid_at' => '2026-09-12',
        'produced_at'     => null,
        'qc_passed_at'    => '2026-09-14',
    ]);

    expect(segmentOf($journey, 'ready')['checks'])->toBe([
        ['key' => 'balance', 'label' => 'Balance', 'done' => true, 'date' => '2026-09-12'],
        ['key' => 'packing', 'label' => 'Packing', 'done' => false, 'date' => null],
        ['key' => 'qc', 'label' => 'QC', 'done' => true, 'date' => '2026-09-14'],
    ])->and(segmentOf(journeyFor([]), 'ready'))->not->toHaveKey('checks');
});

it('reads the agent PO journey settings with defaults', function () {
    $warehouse = App\Models\SupplyChain\Agent::poJourneyFromSettings(['receives_goods_in_own_warehouse' => true]);
    $forwarder = App\Models\SupplyChain\Agent::poJourneyFromSettings([]);
    $custom    = App\Models\SupplyChain\Agent::poJourneyFromSettings(['po_journey' => ['cho_mode' => 'forwarder', 'ready_checks' => ['qc', 'nonsense'], 'stages' => ['ready' => ['label' => 'Mine', 'days' => 12]]]], true);

    expect($warehouse['cho_mode'])->toBe('next_container')
        ->and($forwarder['cho_mode'])->toBe('forwarder')
        ->and($forwarder['ready_checks'])->toBe(['balance', 'packing', 'qc', 'compliance'])
        ->and($forwarder['stages']['ready'])->toBe(['label' => 'Ready to Roll', 'description' => 'Final checks', 'days' => null])
        ->and($custom['cho_mode'])->toBe('forwarder')
        ->and($custom['ready_checks'])->toBe(['qc'])
        ->and($custom['stages']['ready'])->toBe(['label' => 'Mine', 'description' => null, 'days' => 12]);
});
