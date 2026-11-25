<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-16h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Api\Group\Offline\CheckMobileOfflineWaitingNotice;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineEngine;
use App\Actions\Api\Group\Offline\MobileOfflineHandlerRegistry;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Api\Group\Offline\PurgeMobileOfflineOperations;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineDevice;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\MobileOfflineOperationEffect;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';

class MobileOfflineSyncFakeHandler implements MobileOfflineHandler
{
    public int $applyCalls = 0;

    public function __construct(private readonly string $name, private readonly string $kind = 'rules')
    {
    }

    public function operation(): string
    {
        return $this->name;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'rows'         => ['required', 'array', 'min:1'],
            'rows.*'       => ['integer'],
            'quantity'     => ['required', 'numeric', 'gt:0'],
            'outcome'      => ['sometimes', 'in:applied,refused,partly,audit,refuse_until_stock'],
            'reason'       => ['sometimes', 'string'],
            'versions'     => ['sometimes', 'array'],
            'task'         => ['sometimes', 'boolean'],
            'rollbackable' => ['sometimes', 'boolean'],
        ])->validate();
    }

    public function subject(array $payload): array
    {
        return [
            'subject_type' => 'LocationOrgStock',
            'subject_id'   => (int) $payload['rows'][0],
            'parent_type'  => 'Location',
            'parent_id'    => (int) LocationOrgStock::whereKey($payload['rows'][0])->value('location_id'),
        ];
    }

    public function touchedRows(array $payload): array
    {
        return array_map(fn ($id) => array_filter([
            't'  => 'LocationOrgStock',
            'id' => (int) $id,
            'v'  => isset($payload['versions'][$id]) ? (int) $payload['versions'][$id] : null,
        ], fn ($value) => $value !== null), $payload['rows']);
    }

    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $this->applyCalls++;
        $quantity = (float) $payload['quantity'];
        $rows     = LocationOrgStock::query()->whereIn('id', $payload['rows'])->orderBy('id')->lockForUpdate()->get();

        switch ($payload['outcome'] ?? 'applied') {
            case 'refused':
                if (!empty($payload['task'])) {
                    $context->requestStockCheck($rows->first(), 'Goods are on the shelf.');
                }

                return MobileOfflineOutcome::refused($payload['reason'] ?? 'location_empty', MobileOfflineOutcome::INSTRUCTION_KEEP_IN_TOTE, 'Refused by the fake.', $quantity);
            case 'refuse_until_stock':
                if ((float) $rows->first()->quantity < $quantity) {
                    $context->requestStockCheck($rows->first(), 'Not enough stock.');

                    return MobileOfflineOutcome::refused('not_enough_stock', MobileOfflineOutcome::INSTRUCTION_KEEP_IN_TOTE, 'Not enough.', $quantity);
                }
                break;
            case 'partly':
                DB::table('location_org_stocks')->where('id', $rows->first()->id)->update(['quantity' => (float) $rows->first()->quantity - 1]);

                return MobileOfflineOutcome::partlyApplied($quantity, 1, 'not_enough_stock', MobileOfflineOutcome::INSTRUCTION_PUT_BACK, 'Partly.');
            case 'audit':
                foreach ($rows as $row) {
                    if (!$context->ownChainAllows($row, (int) ($payload['versions'][$row->id] ?? 0))) {
                        return MobileOfflineOutcome::conflict('other_change', 'Changed since the download.', ['rows' => [$row->id => (float) $row->quantity]]);
                    }
                }
                foreach ($rows as $row) {
                    DB::table('location_org_stocks')->where('id', $row->id)->update(['quantity' => $quantity, 'audited_at' => now()]);
                }

                return MobileOfflineOutcome::applied($quantity, $quantity, false, 'Counted.');
        }

        $countedSince = false;
        foreach ($rows as $row) {
            if ($context->countedSince($row)) {
                $countedSince = true;
                continue;
            }
            DB::table('location_org_stocks')->where('id', $row->id)->update(['quantity' => (float) $row->quantity - $quantity]);
        }

        return MobileOfflineOutcome::applied($quantity, $quantity, $countedSince, 'Moved.');
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return (bool) ($operation->payload['rollbackable'] ?? true);
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        foreach ($operation->payload['rows'] as $id) {
            DB::table('location_org_stocks')->where('id', $id)->increment('quantity', (float) $operation->payload['quantity']);
        }
    }
}

function offlineUser(object $ctx, array $extraPermissions = []): User
{
    $warehouseId = $ctx->warehouse->id;

    return actingAsGoodsOutUser($ctx->group, $ctx->warehouse, [], ["stocks.$warehouseId.view", "dispatching.$warehouseId.view", ...$extraPermissions]);
}

function offlineRow(Warehouse $warehouse, float $quantity = 30): LocationOrgStock
{
    $location = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'OFL'.Str::upper(Str::random(6))]));
    $stock    = StoreStock::make()->action($warehouse->group, Stock::factory()->definition());
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);

    $row = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => $quantity, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()],
        strict: false
    );

    return $row->refresh();
}

function offlineAction(int $sequence, array $payload, string $operation = 'move_stock', array $extra = []): array
{
    return array_merge([
        'sequence'     => $sequence,
        'operation'    => $operation,
        'performed_at' => now()->subMinutes(5)->toIso8601ZuluString('millisecond'),
        'time_source'  => 'server',
        'seen_at'      => now()->subMinutes(30)->toIso8601ZuluString('millisecond'),
        'payload'      => $payload,
    ], $extra);
}

function offlineHeaders(string $device): array
{
    return [
        'X-Aiku-App-Key'     => 'mobile-test-key',
        'X-Aiku-Device'      => $device,
        'X-Aiku-Platform'    => 'android',
        'X-Aiku-App-Version' => '1.4.2 (52)',
    ];
}

function offlineSync(object $ctx, array $actions, ?string $device = null): TestResponse
{
    return postJson(route('grp.api.mobile.warehouses.offline.sync', $ctx->warehouse->id), ['actions' => $actions], offlineHeaders($device ?? $ctx->device));
}

function offlineIndex(object $ctx, string $state, ?string $device = null): TestResponse
{
    return getJson(route('grp.api.mobile.offline.operations.index').'?state='.$state, offlineHeaders($device ?? $ctx->device));
}

function offlineResolve(object $ctx, int $operationId, string $resolution): TestResponse
{
    return postJson(route('grp.api.mobile.offline.operations.resolve', $operationId), ['resolution' => $resolution], offlineHeaders($ctx->device));
}

function offlineRollback(object $ctx, int $operationId, ?string $reason = null): TestResponse
{
    return postJson(route('grp.api.mobile.offline.operations.rollback', $operationId), array_filter(['reason' => $reason]), offlineHeaders($ctx->device));
}

function offlineOperation(object $ctx, int $sequence, ?User $user = null, ?string $device = null): MobileOfflineOperation
{
    return MobileOfflineOperation::query()
        ->where('device_uuid', $device ?? $ctx->device)
        ->where('user_id', ($user ?? $ctx->user)->id)
        ->where('device_sequence', $sequence)
        ->sole();
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->warehouse    = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $this->user         = offlineUser($this);
    $this->device       = (string) Str::uuid();

    $registry      = app(MobileOfflineHandlerRegistry::class);
    $this->handler = new MobileOfflineSyncFakeHandler('move_stock');
    $this->audit   = new MobileOfflineSyncFakeHandler('audit_location', 'version');
    $registry->swap('move_stock', $this->handler);
    $registry->swap('audit_location', $this->audit);
});

test('sync applies actions in number order, answers resends, voids and stops at a gap', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 2]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 3]),
        offlineAction(3, [], 'void'),
    ])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.results.0.message', 'Moved.')
        ->assertJsonPath('data.results.1.status', 'applied')
        ->assertJsonPath('data.results.2.status', 'void')
        ->assertJsonPath('data.last_sequence', 3)
        ->assertJsonPath('data.open_count', 0);

    expect((float) $row->refresh()->quantity)->toBe(25.0)
        ->and(offlineOperation($this, 3)->state)->toBe('void');

    $second = offlineOperation($this, 2);
    offlineSync($this, [
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 3]),
        offlineAction(5, ['rows' => [$row->id], 'quantity' => 1]),
        offlineAction(6, ['rows' => [$row->id], 'quantity' => 1]),
    ])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'already_done')
        ->assertJsonPath('data.results.0.operation_id', $second->id)
        ->assertJsonPath('data.results.0.applied_quantity', 3)
        ->assertJsonPath('data.results.1.status', 'missing_previous')
        ->assertJsonPath('data.results.1.message', 'Send action 4 first.')
        ->assertJsonCount(2, 'data.results')
        ->assertJsonPath('data.expected_sequence', 4)
        ->assertJsonPath('data.last_sequence', 3);

    expect((float) $row->refresh()->quantity)->toBe(25.0)
        ->and($this->handler->applyCalls)->toBe(2);
});

test('the first number on a new device row is the start, and numbers are kept per user on one device', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [offlineAction(40, ['rows' => [$row->id], 'quantity' => 1])])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.last_sequence', 40);

    $colleague = offlineUser($this);
    offlineSync($this, [offlineAction(1, ['rows' => [$row->id], 'quantity' => 1])])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.last_sequence', 1);

    $colleagueDevice = MobileOfflineDevice::where('device_uuid', $this->device)->where('user_id', $colleague->id)->sole();
    expect($colleagueDevice->last_sequence)->toBe(1)
        ->and($colleagueDevice->platform)->toBe('android')
        ->and($colleagueDevice->app_version)->toBe('1.4.2 (52)')
        ->and(MobileOfflineDevice::where('device_uuid', $this->device)->where('user_id', $this->user->id)->value('last_sequence'))->toBe(40);
});

test('an action is blocked behind an open action of the same person on the same device that touched one of its rows', function () {
    $shared  = offlineRow($this->warehouse);
    $other   = offlineRow($this->warehouse);
    $partial = offlineRow($this->warehouse);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$shared->id], 'quantity' => 2, 'outcome' => 'refused']),
        offlineAction(2, ['rows' => [$shared->id, $other->id], 'quantity' => 1]),
        offlineAction(3, ['rows' => [$other->id], 'quantity' => 1]),
        offlineAction(4, ['rows' => [$partial->id], 'quantity' => 3, 'outcome' => 'partly']),
        offlineAction(5, ['rows' => [$partial->id], 'quantity' => 1]),
    ])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'refused')
        ->assertJsonPath('data.results.0.reason', 'location_empty')
        ->assertJsonPath('data.results.0.instruction', 'keep_in_tote')
        ->assertJsonPath('data.results.1.status', 'blocked')
        ->assertJsonPath('data.results.1.blocked_by_sequence', 1)
        ->assertJsonPath('data.results.1.message', 'Waits until action 1 is dealt with.')
        ->assertJsonPath('data.results.2.status', 'blocked')
        ->assertJsonPath('data.results.2.blocked_by_sequence', 2)
        ->assertJsonPath('data.results.3.status', 'partly_applied')
        ->assertJsonPath('data.results.4.status', 'applied')
        ->assertJsonPath('data.open_count', 4);

    expect((float) $other->refresh()->quantity)->toBe(30.0)
        ->and(offlineOperation($this, 2)->blocked_by_id)->toBe(offlineOperation($this, 1)->id);

    offlineSync($this, [offlineAction(1, ['rows' => [$shared->id], 'quantity' => 1])], (string) Str::uuid())
        ->assertJsonPath('data.results.0.status', 'applied');

    offlineUser($this);
    offlineSync($this, [offlineAction(1, ['rows' => [$shared->id], 'quantity' => 1])])
        ->assertJsonPath('data.results.0.status', 'applied');
});

test('an invalid payload uses up its number and is refused as invalid', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id]]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 1], 'no_such_operation'),
        offlineAction(3, ['rows' => [$row->id], 'quantity' => 1]),
    ])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'refused')
        ->assertJsonPath('data.results.0.reason', 'invalid')
        ->assertJsonPath('data.results.0.instruction', 'none')
        ->assertJsonPath('data.results.0.message', 'This action could not be read: The quantity field is required.')
        ->assertJsonPath('data.results.1.reason', 'invalid')
        ->assertJsonPath('data.results.2.status', 'applied')
        ->assertJsonPath('data.last_sequence', 3);

    expect(offlineOperation($this, 1)->touched_rows)->toBeNull();
});

test('the journal row keeps every column and the effects carry the location row versions', function () {
    $row     = offlineRow($this->warehouse);
    $version = (int) $row->version;
    $seenAt      = now()->subMinutes(30)->startOfSecond();
    $performedAt = now()->subMinutes(10)->startOfSecond();

    $response = offlineSync($this, [offlineAction(1, ['rows' => [$row->id], 'quantity' => 3], extra: [
        'seen_at'      => $seenAt->toIso8601ZuluString(),
        'performed_at' => $performedAt->toIso8601ZuluString(),
    ])])->assertOk();

    $operation = offlineOperation($this, 1);
    $response->assertJsonPath('data.results.0', [
        'sequence'            => 1,
        'status'              => 'applied',
        'operation_id'        => $operation->id,
        'operation'           => 'move_stock',
        'reason'              => null,
        'instruction'         => 'none',
        'message'             => 'Moved.',
        'requested_quantity'  => 3,
        'applied_quantity'    => 3,
        'counted_since'       => false,
        'task_reference'      => null,
        'conflicting_user'    => null,
        'blocked_by_sequence' => null,
        'versions'            => [['location_org_stock_id' => $row->id, 'version' => $version + 1, 'quantity' => 27]],
    ]);

    expect($operation->only(['group_id', 'organisation_id', 'warehouse_id', 'user_id', 'platform', 'app_version', 'operation', 'kind', 'subject_type', 'subject_id', 'parent_type', 'parent_id', 'state']))->toBe([
        'group_id'        => $this->warehouse->group_id,
        'organisation_id' => $this->warehouse->organisation_id,
        'warehouse_id'    => $this->warehouse->id,
        'user_id'         => $this->user->id,
        'platform'        => 'android',
        'app_version'     => '1.4.2 (52)',
        'operation'       => 'move_stock',
        'kind'            => 'rules',
        'subject_type'    => 'LocationOrgStock',
        'subject_id'      => $row->id,
        'parent_type'     => 'Location',
        'parent_id'       => $row->location_id,
        'state'           => 'applied',
    ])
        ->and($operation->touched_rows)->toEqual([['t' => 'LocationOrgStock', 'id' => $row->id]])
        ->and($operation->payload)->toBe(['rows' => [$row->id], 'quantity' => 3])
        ->and($operation->requested_quantity)->toBe(3.0)
        ->and($operation->applied_quantity)->toBe(3.0)
        ->and($operation->seen_at->equalTo($seenAt))->toBeTrue()
        ->and($operation->performed_at->equalTo($performedAt))->toBeTrue()
        ->and($operation->synced_at)->not->toBeNull()
        ->and($operation->server_snapshot['answer'])->toEqual(['instruction' => 'none', 'message' => 'Moved.']);

    $effect = MobileOfflineOperationEffect::where('mobile_offline_operation_id', $operation->id)->sole();
    expect($effect->only(['model_type', 'model_id', 'effect', 'before', 'after']))->toEqual([
        'model_type' => 'LocationOrgStock',
        'model_id'   => $row->id,
        'effect'     => 'updated',
        'before'     => ['version' => $version, 'quantity' => 30.0],
        'after'      => ['version' => $version + 1, 'quantity' => 27.0],
    ]);
});

test('an audit passes over the person\'s own earlier actions but not over someone else\'s change in the middle', function () {
    $row     = offlineRow($this->warehouse, 12);
    $version = (int) $row->version;

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 2]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 15, 'outcome' => 'audit', 'versions' => [$row->id => $version]], 'audit_location'),
    ])
        ->assertJsonPath('data.results.1.status', 'applied');
    expect((float) $row->refresh()->quantity)->toBe(15.0)
        ->and(offlineOperation($this, 2)->touched_rows)->toEqual([['t' => 'LocationOrgStock', 'id' => $row->id, 'v' => $version]]);

    $other        = offlineRow($this->warehouse, 12);
    $otherVersion = (int) $other->version;
    DB::table('location_org_stocks')->where('id', $other->id)->update(['quantity' => 10]);

    offlineSync($this, [
        offlineAction(3, ['rows' => [$other->id], 'quantity' => 1]),
        offlineAction(4, ['rows' => [$other->id], 'quantity' => 15, 'outcome' => 'audit', 'versions' => [$other->id => $otherVersion]], 'audit_location'),
    ])
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.results.1.status', 'conflict')
        ->assertJsonPath('data.results.1.reason', 'other_change')
        ->assertJsonPath('data.results.1.instruction', 'recount');

    expect((float) $other->refresh()->quantity)->toBe(9.0);
});

test('a row counted after the action is recorded without a stock movement', function () {
    $row = offlineRow($this->warehouse);
    DB::table('location_org_stocks')->where('id', $row->id)->update(['audited_at' => now()->subMinutes(2)]);

    offlineSync($this, [offlineAction(1, ['rows' => [$row->id], 'quantity' => 2], extra: ['performed_at' => now()->subMinutes(5)->toIso8601ZuluString()])])
        ->assertJsonPath('data.results.0.status', 'applied')
        ->assertJsonPath('data.results.0.counted_since', true);

    expect((float) $row->refresh()->quantity)->toBe(30.0)
        ->and(offlineOperation($this, 1)->counted_since)->toBeTrue();
});

test('performed_at is clamped between seen_at and synced_at, with a 30 day floor without seen_at', function () {
    $row = offlineRow($this->warehouse);
    $this->travelTo(now()->startOfSecond());

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 1], extra: ['seen_at' => now()->subHour()->toIso8601ZuluString(), 'performed_at' => now()->subHours(3)->toIso8601ZuluString()]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 1], extra: ['performed_at' => now()->addHours(2)->toIso8601ZuluString()]),
        offlineAction(3, ['rows' => [$row->id], 'quantity' => 1], extra: ['seen_at' => null, 'time_source' => 'device', 'performed_at' => now()->subDays(60)->toIso8601ZuluString()]),
    ])->assertOk();

    expect(offlineOperation($this, 1)->performed_at->equalTo(now()->subHour()))->toBeTrue()
        ->and(offlineOperation($this, 2)->performed_at->equalTo(now()))->toBeTrue()
        ->and(offlineOperation($this, 3)->performed_at->equalTo(now()->subDays(30)))->toBeTrue();
});

test('the open list shows this person\'s open actions on this device with labels, record and resolutions', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 2]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 2, 'outcome' => 'refused', 'reason' => 'location_empty', 'task' => true]),
        offlineAction(3, ['rows' => [$row->id], 'quantity' => 2]),
    ])->assertOk()
        ->assertJsonPath('data.results.1.task_reference', fn ($reference) => str_starts_with((string) $reference, 'TASK-'));
    offlineSync($this, [offlineAction(1, ['rows' => [$row->id], 'quantity' => 1, 'outcome' => 'refused'])], (string) Str::uuid());

    $task = StaffTask::find(offlineOperation($this, 2)->staff_task_id);
    expect($task->department)->toBe('warehouse')
        ->and($task->requester_id)->toBe($this->user->id)
        ->and($task->model_type)->toBe('Location')
        ->and($task->model_id)->toBe($row->location_id)
        ->and($task->data['organisation_id'])->toBe($this->warehouse->organisation_id)
        ->and($task->description)->toContain($this->warehouse->name);

    $open = offlineIndex($this, 'open')->assertOk()->json('data');
    expect(collect($open)->pluck('sequence')->all())->toBe([2, 3])
        ->and($open[0])->toMatchArray([
            'state'        => 'refused',
            'status'       => 'refused',
            'resolution'   => null,
            'labels'       => ['title' => $row->location->code, 'subtitle' => trim($row->orgStock->code.' '.$row->orgStock->name), 'location_code' => $row->location->code],
            'record'       => ['type' => 'location', 'id' => $row->location_id],
            'resolutions'  => ['put_back', 'done', 'retry'],
            'can_rollback' => false,
        ])
        ->and($open[0]['performed_at'])->not->toBeNull()
        ->and($open[0]['item'])->toMatchArray([
            'sko_code'         => $row->orgStock->code,
            'sko_name'         => $row->orgStock->name,
            'record_type'      => 'location',
            'record_reference' => $row->location->code,
            'line'             => null,
            'quantities'       => ['stock_now' => (float) $row->refresh()->quantity],
        ])
        ->and($open[1]['state'])->toBe('blocked')
        ->and($open[1]['resolutions'])->toBe([]);

    expect(collect(offlineIndex($this, 'recent')->assertOk()->json('data'))->pluck('sequence')->all())->toBe([3, 2, 1]);

    getJson(route('grp.api.mobile.offline.operations.index').'?state=open', ['X-Aiku-App-Key' => 'mobile-test-key'])->assertUnprocessable();
});

test('a refused not picked or waiting action has nothing to put back', function () {
    $engine  = app(MobileOfflineEngine::class);
    $refused = fn (string $operation) => (new MobileOfflineOperation())->forceFill([
        'user_id'        => $this->user->id,
        'warehouse_id'   => $this->warehouse->id,
        'operation'      => $operation,
        'state'          => MobileOfflineOperation::STATE_REFUSED,
        'outcome_reason' => 'over_required',
    ]);

    expect($engine->allowedResolutions($refused('not_picked'), $this->user))->toBe(['done'])
        ->and($engine->allowedResolutions($refused('set_waiting_warehouse'), $this->user))->toBe(['done'])
        ->and($engine->allowedResolutions($refused('set_waiting_crm'), $this->user))->toBe(['done'])
        ->and($engine->allowedResolutions($refused('pick'), $this->user))->toBe(['put_back', 'done']);
});

test('each resolution closes the right kind of open action', function () {
    $row = offlineRow($this->warehouse, 12);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 2, 'outcome' => 'refused', 'reason' => 'note_cancelled']),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 3, 'outcome' => 'partly']),
    ]);
    offlineSync($this, [offlineAction(3, ['rows' => [$row->id], 'quantity' => 1])], (string) Str::uuid());

    offlineResolve($this, offlineOperation($this, 1)->id, 'retry')->assertUnprocessable();
    offlineResolve($this, offlineOperation($this, 1)->id, 'put_back')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'resolved')
        ->assertJsonPath('data.operation.resolution', 'put_back');
    offlineResolve($this, offlineOperation($this, 2)->id, 'done')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'resolved');
    expect(offlineOperation($this, 2)->resolved_by_user_id)->toBe($this->user->id);

    $conflicted = offlineRow($this->warehouse, 12);
    DB::table('location_org_stocks')->where('id', $conflicted->id)->update(['quantity' => 11]);
    $audit = fn (int $sequence) => offlineAction($sequence, ['rows' => [$conflicted->id], 'quantity' => 20, 'outcome' => 'audit', 'versions' => [$conflicted->id => (int) $conflicted->version]], 'audit_location');

    offlineSync($this, [$audit(3), $audit(4), $audit(5)])
        ->assertJsonPath('data.results.0.status', 'conflict')
        ->assertJsonPath('data.results.1.status', 'blocked');

    offlineResolve($this, offlineOperation($this, 3)->id, 'keep_count')->assertUnprocessable();
    offlineResolve($this, offlineOperation($this, 3)->id, 'recount')
        ->assertOk()
        ->assertJsonPath('data.operation.resolution', 'recount')
        ->assertJsonPath('data.results.0.sequence', 4)
        ->assertJsonPath('data.results.0.status', 'conflict')
        ->assertJsonPath('data.results.1.status', 'blocked');

    offlineResolve($this, offlineOperation($this, 4)->id, 'discard')
        ->assertOk()
        ->assertJsonPath('data.operation.resolution', 'discard')
        ->assertJsonPath('data.results.0.status', 'conflict');

    $supervisor = offlineUser($this, ['supervisor-stocks.'.$this->warehouse->id]);
    offlineResolve($this, offlineOperation($this, 5)->id, 'keep_count')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'resolved')
        ->assertJsonPath('data.operation.resolution', 'keep_count');

    expect((float) $conflicted->refresh()->quantity)->toBe(20.0)
        ->and(offlineOperation($this, 5)->resolved_by_user_id)->toBe($supervisor->id);
});

test('a retry runs the handler again with the stored payload, keeps its task and releases the actions blocked behind it', function () {
    $row = offlineRow($this->warehouse, 0);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 2, 'outcome' => 'refuse_until_stock']),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 1]),
    ])
        ->assertJsonPath('data.results.0.reason', 'not_enough_stock')
        ->assertJsonPath('data.results.1.status', 'blocked');
    $first  = offlineOperation($this, 1);
    $taskId = $first->staff_task_id;
    expect($taskId)->not->toBeNull();

    offlineResolve($this, $first->id, 'retry')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'refused')
        ->assertJsonPath('data.results', []);
    expect(offlineOperation($this, 1)->staff_task_id)->toBe($taskId)
        ->and(StaffTask::where('model_type', 'Location')->where('model_id', $row->location_id)->count())->toBe(1);

    DB::table('location_org_stocks')->where('id', $row->id)->update(['quantity' => 10]);

    offlineResolve($this, $first->id, 'retry')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'resolved')
        ->assertJsonPath('data.operation.resolution', 'retry')
        ->assertJsonPath('data.results.0.sequence', 2)
        ->assertJsonPath('data.results.0.status', 'applied');

    expect($this->handler->applyCalls)->toBe(4)
        ->and((float) $row->refresh()->quantity)->toBe(7.0)
        ->and(MobileOfflineOperationEffect::where('mobile_offline_operation_id', $first->id)->count())->toBe(1);
});

test('a rollback is allowed within 24 hours to the person or a supervisor when the handler allows it', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 4]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 1, 'rollbackable' => false]),
        offlineAction(3, ['rows' => [$row->id], 'quantity' => 2]),
        offlineAction(4, ['rows' => [$row->id], 'quantity' => 3]),
    ]);
    expect((float) $row->refresh()->quantity)->toBe(20.0);

    $first = offlineOperation($this, 1);
    expect(collect(offlineIndex($this, 'recent')->json('data'))->firstWhere('sequence', 1)['can_rollback'])->toBeTrue();

    offlineRollback($this, $first->id, 'Wrong shelf')
        ->assertOk()
        ->assertJsonPath('data.operation.state', 'rolled_back')
        ->assertJsonPath('data.operation.can_rollback', false);

    $rollback = MobileOfflineOperation::where('rollback_of_id', $first->id)->sole();
    expect((float) $row->refresh()->quantity)->toBe(24.0)
        ->and($rollback->operation)->toBe('rollback')
        ->and($rollback->user_id)->toBe($this->user->id)
        ->and($rollback->device_uuid)->toBe($this->device)
        ->and($rollback->device_sequence)->toBeNull()
        ->and($first->refresh()->rollback_reason)->toBe('Wrong shelf')
        ->and($first->rolled_back_by_user_id)->toBe($this->user->id)
        ->and(MobileOfflineOperationEffect::where('mobile_offline_operation_id', $rollback->id)->value('effect'))->toBe('updated');

    offlineRollback($this, $first->id)->assertUnprocessable();
    offlineRollback($this, offlineOperation($this, 2)->id)->assertUnprocessable();

    $owner = $this->user;
    offlineUser($this);
    offlineRollback($this, offlineOperation($this, 3, $owner)->id)->assertNotFound();

    offlineUser($this, ['supervisor-stocks.'.$this->warehouse->id]);
    offlineRollback($this, offlineOperation($this, 3, $owner)->id)->assertOk();

    $this->travel(25)->hours();
    offlineRollback($this, offlineOperation($this, 4, $owner)->id)->assertUnprocessable();
});

test('the waiting notice queues a check that asks the warehouse only when the person did not sync', function () {
    Queue::fake();
    $absent = createMobileAppUser($this->group);

    postJson(route('grp.api.mobile.offline.waiting_notice'), ['user_id' => $absent->id, 'count' => 3, 'oldest_performed_at' => now()->subHour()->toIso8601ZuluString()], offlineHeaders($this->device))
        ->assertNoContent();

    CheckMobileOfflineWaitingNotice::assertPushed(fn ($action, array $parameters, $job) => $parameters[0] === $this->device
        && $parameters[1] === $absent->id
        && $parameters[2] === $this->user->id
        && $parameters[3] === 3
        && $job->delay !== null);

    $noticedAt = now()->toIso8601String();
    $this->travel(24)->hours();

    $task = CheckMobileOfflineWaitingNotice::make()->handle($this->device, $absent->id, $this->user->id, 3, null, $noticedAt);
    expect($task)->not->toBeNull()
        ->and($task->department)->toBe('warehouse')
        ->and($task->requester_id)->toBe($this->user->id)
        ->and($task->subject)->toStartWith('Offline actions of ')
        ->and($task->description)->toContain(' left 3 actions unsent on a shared phone, the oldest done ');

    $single = CheckMobileOfflineWaitingNotice::make()->handle($this->device, $absent->id, $this->user->id, 1, null, $noticedAt);
    expect($single->subject)->toStartWith('An offline action of ')
        ->and($single->description)->toContain(' left 1 action unsent on a shared phone, done ')
        ->and($single->description)->toContain('It is sent only when ');

    DB::table('mobile_offline_devices')->insert([
        'device_uuid' => $this->device, 'user_id' => $absent->id, 'group_id' => $this->group->id, 'platform' => 'android', 'last_sequence' => 3,
        'created_at'  => now(), 'updated_at' => now(),
    ]);
    expect(CheckMobileOfflineWaitingNotice::make()->handle($this->device, $absent->id, $this->user->id, 3, null, $noticedAt))->toBeNull();

    postJson(route('grp.api.mobile.offline.waiting_notice'), ['user_id' => $absent->id, 'count' => 3], ['X-Aiku-App-Key' => 'mobile-test-key'])->assertUnprocessable();
});

test('the purge expires week-old open actions with the ones blocked behind them and deletes old closed rows and devices', function () {
    $row     = offlineRow($this->warehouse);
    $fresh   = offlineRow($this->warehouse);
    $oldDays = now()->subDays(8);

    offlineSync($this, [
        offlineAction(1, ['rows' => [$row->id], 'quantity' => 1]),
        offlineAction(2, ['rows' => [$row->id], 'quantity' => 1, 'outcome' => 'refused']),
        offlineAction(3, ['rows' => [$fresh->id], 'quantity' => 1, 'outcome' => 'refused']),
        offlineAction(4, ['rows' => [$fresh->id], 'quantity' => 1]),
    ]);
    MobileOfflineOperation::where('device_uuid', $this->device)->whereIn('device_sequence', [1, 2])->update(['synced_at' => $oldDays]);
    offlineSync($this, [offlineAction(5, ['rows' => [$row->id], 'quantity' => 1])])->assertJsonPath('data.results.0.status', 'blocked');

    $oldDevice = (string) Str::uuid();
    DB::table('mobile_offline_devices')->insert([
        'device_uuid' => $oldDevice, 'user_id' => $this->user->id, 'group_id' => $this->group->id, 'platform' => 'android', 'last_sequence' => 9,
        'created_at'  => now()->subDays(400), 'updated_at' => now()->subDays(400),
    ]);

    $tasksBefore = StaffTask::where('requester_id', $this->user->id)->count();
    $result      = PurgeMobileOfflineOperations::make()->handle();

    expect($result['expired'])->toBe(2)
        ->and(offlineOperation($this, 2)->state)->toBe('expired')
        ->and(offlineOperation($this, 2)->expired_at)->not->toBeNull()
        ->and(offlineOperation($this, 5)->state)->toBe('expired')
        ->and(offlineOperation($this, 3)->state)->toBe('refused')
        ->and(MobileOfflineOperation::where('device_uuid', $this->device)->where('device_sequence', 1)->exists())->toBeFalse()
        ->and(offlineOperation($this, 4)->state)->toBe('blocked')
        ->and(MobileOfflineDevice::where('device_uuid', $oldDevice)->exists())->toBeFalse()
        ->and(MobileOfflineDevice::where('device_uuid', $this->device)->exists())->toBeTrue()
        ->and(StaffTask::where('requester_id', $this->user->id)->count())->toBe($tasksBefore + 1);

    $this->travel(8)->days();
    PurgeMobileOfflineOperations::make()->handle();

    expect(MobileOfflineOperation::where('device_uuid', $this->device)->whereIn('device_sequence', [2, 5])->exists())->toBeFalse();
    $this->artisan('mobile-offline:purge')->assertSuccessful();
});

test('the offline endpoints need a signed-in mobile user, the device header and an authorised warehouse', function () {
    $row = offlineRow($this->warehouse);

    offlineSync($this, [offlineAction(1, ['rows' => [$row->id], 'quantity' => 1])], 'not-a-uuid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('X-Aiku-Device');
    postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), ['actions' => array_fill(0, 51, offlineAction(1, []))], offlineHeaders($this->device))
        ->assertUnprocessable();
    postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), [], offlineHeaders($this->device))
        ->assertUnprocessable();

    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    postJson(route('grp.api.mobile.warehouses.offline.sync', $otherWarehouse->id), ['actions' => []], offlineHeaders($this->device))
        ->assertNotFound();

    offlineSync($this, [])->assertOk()->assertJsonPath('data.results', [])->assertJsonPath('data.last_sequence', 0);

    Auth::forgetGuards();
    $this->app['auth']->guard('sanctum')->forgetUser();
    offlineSync($this, [])->assertUnauthorized();
    offlineIndex($this, 'open')->assertUnauthorized();
});
