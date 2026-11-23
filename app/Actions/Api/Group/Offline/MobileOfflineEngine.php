<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineDevice;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\MobileOfflineOperationEffect;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileOfflineEngine
{
    public const string RESOLUTION_PUT_BACK = 'put_back';
    public const string RESOLUTION_DONE = 'done';
    public const string RESOLUTION_RETRY = 'retry';
    public const string RESOLUTION_RECOUNT = 'recount';
    public const string RESOLUTION_DISCARD = 'discard';
    public const string RESOLUTION_KEEP_COUNT = 'keep_count';

    public const array RESOLUTIONS = [
        self::RESOLUTION_PUT_BACK,
        self::RESOLUTION_DONE,
        self::RESOLUTION_RETRY,
        self::RESOLUTION_RECOUNT,
        self::RESOLUTION_DISCARD,
        self::RESOLUTION_KEEP_COUNT,
    ];

    public const array RETRY_REASONS = ['location_empty', 'not_enough_stock'];

    public const array NOTHING_TO_PUT_BACK_OPERATIONS = ['not_picked', 'set_waiting_warehouse', 'set_waiting_crm'];

    public const string REASON_INVALID = 'invalid';

    public const int ROLLBACK_HOURS = 24;

    public const int SEEN_AT_FLOOR_DAYS = 30;

    public function __construct(private readonly MobileOfflineHandlerRegistry $registry)
    {
    }

    public static function isSupervisor(User $user, int $warehouseId): bool
    {
        return $user->authTo("supervisor-stocks.$warehouseId");
    }

    public function ensureDevice(MobileOfflineDeviceHeaders $headers, User $user): void
    {
        DB::table('mobile_offline_devices')->insertOrIgnore([
            'device_uuid'   => $headers->deviceUuid,
            'user_id'       => $user->id,
            'group_id'      => $user->group_id,
            'platform'      => $headers->platform,
            'app_version'   => $headers->appVersion,
            'last_sequence' => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        MobileOfflineDevice::query()
            ->where('device_uuid', $headers->deviceUuid)
            ->where('user_id', $user->id)
            ->update(array_filter([
                'platform'    => $headers->platform,
                'app_version' => $headers->appVersion,
                'updated_at'  => now(),
            ], fn ($value) => $value !== null));
    }

    /**
     * @param array{sequence: int, operation: string, performed_at?: ?string, time_source?: ?string, seen_at?: ?string, payload: array<string, mixed>} $action
     * @return array{operation: ?MobileOfflineOperation, status: string, sequence: int, expected_sequence: ?int}
     * @throws Throwable
     */
    public function syncAction(MobileOfflineDeviceHeaders $headers, User $user, Warehouse $warehouse, array $action, CarbonImmutable $syncedAt): array
    {
        return DB::transaction(function () use ($headers, $user, $warehouse, $action, $syncedAt) {
            $device   = $this->lockDevice($headers->deviceUuid, $user->id);
            $sequence = (int) $action['sequence'];

            if ($sequence <= $device->last_sequence) {
                $stored = MobileOfflineOperation::query()
                    ->where('device_uuid', $headers->deviceUuid)
                    ->where('user_id', $user->id)
                    ->where('device_sequence', $sequence)
                    ->first();

                return ['operation' => $stored, 'status' => 'already_done', 'sequence' => $sequence, 'expected_sequence' => null];
            }

            if ($device->last_sequence > 0 && $sequence > $device->last_sequence + 1) {
                return ['operation' => null, 'status' => 'missing_previous', 'sequence' => $sequence, 'expected_sequence' => $device->last_sequence + 1];
            }

            $seenAt    = isset($action['seen_at']) ? CarbonImmutable::parse($action['seen_at']) : null;
            $operation = new MobileOfflineOperation([
                'group_id'                 => $warehouse->group_id,
                'organisation_id'          => $warehouse->organisation_id,
                'warehouse_id'             => $warehouse->id,
                'user_id'                  => $user->id,
                'personal_access_token_id' => $headers->personalAccessTokenId,
                'device_uuid'              => $headers->deviceUuid,
                'device_sequence'          => $sequence,
                'platform'                 => $headers->platform,
                'app_version'              => $headers->appVersion,
                'operation'                => (string) $action['operation'],
                'payload'                  => $action['payload'] ?? [],
                'seen_at'                  => $seenAt,
                'performed_at'             => $this->clampPerformedAt($action['performed_at'] ?? null, $seenAt, $syncedAt),
                'synced_at'                => $syncedAt,
                'counted_since'            => false,
            ]);
            $operation->setRelation('user', $user);
            $operation->setRelation('warehouse', $warehouse);

            if ($operation->operation === MobileOfflineOperation::OPERATION_VOID) {
                $operation->payload = [];
                $this->storeAnswer($operation, MobileOfflineOperation::STATE_VOID, null, MobileOfflineOutcome::INSTRUCTION_NONE, __('Undone on the phone before it was sent.'));
                $operation->save();
            } else {
                $this->run($operation);
            }

            $device->update(['last_sequence' => $sequence]);

            return ['operation' => $operation, 'status' => $operation->state, 'sequence' => $sequence, 'expected_sequence' => null];
        });
    }

    public function clampPerformedAt(?string $performedAt, ?CarbonImmutable $seenAt, CarbonImmutable $syncedAt): CarbonImmutable
    {
        $floor = $seenAt ?? $syncedAt->subDays(self::SEEN_AT_FLOOR_DAYS);
        if ($floor->greaterThan($syncedAt)) {
            $floor = $syncedAt;
        }

        $performed = $performedAt ? CarbonImmutable::parse($performedAt) : $syncedAt;

        return match (true) {
            $performed->lessThan($floor)       => $floor,
            $performed->greaterThan($syncedAt) => $syncedAt,
            default                            => $performed,
        };
    }

    /**
     * @return Collection<int, MobileOfflineOperation>
     * @throws Throwable
     */
    public function rerunBlocked(?string $deviceUuid, int $userId): Collection
    {
        if ($deviceUuid === null) {
            return collect();
        }

        $ids = MobileOfflineOperation::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $userId)
            ->where('state', MobileOfflineOperation::STATE_BLOCKED)
            ->orderBy('device_sequence')
            ->pluck('id');

        return $ids->map(fn (int $id) => DB::transaction(function () use ($id, $deviceUuid, $userId) {
            $this->lockDevice($deviceUuid, $userId);
            $operation = MobileOfflineOperation::query()->with(['user', 'warehouse'])->lockForUpdate()->find($id);
            if (!$operation || $operation->state !== MobileOfflineOperation::STATE_BLOCKED) {
                return null;
            }

            $this->run($operation);

            return $operation;
        }))->filter()->values();
    }

    /**
     * @return array<int, string>
     */
    public function allowedResolutions(MobileOfflineOperation $operation, User $user): array
    {
        $isSupervisor = self::isSupervisor($user, $operation->warehouse_id);
        if ($operation->user_id !== $user->id && !$isSupervisor) {
            return [];
        }

        return match ($operation->state) {
            MobileOfflineOperation::STATE_REFUSED => array_values(array_filter([
                in_array($operation->operation, self::NOTHING_TO_PUT_BACK_OPERATIONS, true) ? null : self::RESOLUTION_PUT_BACK,
                self::RESOLUTION_DONE,
                in_array($operation->outcome_reason, self::RETRY_REASONS, true) ? self::RESOLUTION_RETRY : null,
            ])),
            MobileOfflineOperation::STATE_PARTLY_APPLIED => [self::RESOLUTION_PUT_BACK, self::RESOLUTION_DONE],
            MobileOfflineOperation::STATE_CONFLICT       => array_values(array_filter([
                self::RESOLUTION_RECOUNT,
                self::RESOLUTION_DISCARD,
                $isSupervisor ? self::RESOLUTION_KEEP_COUNT : null,
            ])),
            default => [],
        };
    }

    /**
     * @return array{operation: MobileOfflineOperation, reruns: Collection<int, MobileOfflineOperation>}
     * @throws Throwable
     */
    public function resolve(MobileOfflineOperation $operation, string $resolution, User $by): array
    {
        $operation = DB::transaction(function () use ($operation, $resolution, $by) {
            if ($operation->device_uuid) {
                $this->lockDevice($operation->device_uuid, $operation->user_id);
            }
            $operation = MobileOfflineOperation::query()->with(['user', 'warehouse'])->lockForUpdate()->findOrFail($operation->id);

            if (!in_array($resolution, $this->allowedResolutions($operation, $by), true)) {
                throw ValidationException::withMessages(['resolution' => __('This action cannot be closed that way.')]);
            }

            if (in_array($resolution, [self::RESOLUTION_RETRY, self::RESOLUTION_KEEP_COUNT], true)) {
                $this->run($operation, checkBlocking: false, keepCount: $resolution === self::RESOLUTION_KEEP_COUNT);
                if ($operation->state !== MobileOfflineOperation::STATE_APPLIED) {
                    return $operation;
                }
            }

            $operation->update([
                'state'               => MobileOfflineOperation::STATE_RESOLVED,
                'resolution'          => $resolution,
                'resolved_by_user_id' => $by->id,
                'resolved_at'         => now(),
            ]);

            return $operation;
        });

        $reruns = in_array($operation->state, MobileOfflineOperation::BLOCKING_STATES, true)
            ? collect()
            : $this->rerunBlocked($operation->device_uuid, $operation->user_id);

        return ['operation' => $operation, 'reruns' => $reruns];
    }

    public function canRollback(MobileOfflineOperation $operation, User $user, bool $withHandlerCheck = true): bool
    {
        if (in_array($operation->operation, [MobileOfflineOperation::OPERATION_VOID, MobileOfflineOperation::OPERATION_ROLLBACK], true)
            || !in_array($operation->state, [MobileOfflineOperation::STATE_APPLIED, MobileOfflineOperation::STATE_PARTLY_APPLIED, MobileOfflineOperation::STATE_RESOLVED], true)
            || $operation->rolled_back_at !== null
            || $operation->synced_at === null
            || $operation->synced_at->lessThan(now()->subHours(self::ROLLBACK_HOURS))
            || ($operation->user_id !== $user->id && !self::isSupervisor($user, $operation->warehouse_id))) {
            return false;
        }

        if (!$withHandlerCheck) {
            return true;
        }

        $handler = $this->handler($operation->operation);

        return $handler !== null && $operation->effects()->exists() && $handler->canRollback($operation);
    }

    /**
     * @throws Throwable
     */
    public function rollback(MobileOfflineOperation $operation, User $by, ?string $reason, MobileOfflineDeviceHeaders $headers): MobileOfflineOperation
    {
        return DB::transaction(function () use ($operation, $by, $reason, $headers) {
            $operation = MobileOfflineOperation::query()->with(['user', 'warehouse'])->lockForUpdate()->findOrFail($operation->id);

            if (!$this->canRollback($operation, $by)) {
                throw ValidationException::withMessages(['operation' => __('This action can no longer be undone.')]);
            }

            $locationIds = collect($operation->touchedLocationOrgStockIds())
                ->merge($operation->effects()->where('model_type', 'LocationOrgStock')->pluck('model_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $before = self::locationRows($locationIds, lock: true);

            $this->handler($operation->operation)->rollback($operation, $by);

            $after       = self::locationRows($locationIds);
            $rollbackRow = MobileOfflineOperation::create([
                'group_id'                 => $operation->group_id,
                'organisation_id'          => $operation->organisation_id,
                'warehouse_id'             => $operation->warehouse_id,
                'user_id'                  => $by->id,
                'personal_access_token_id' => $headers->personalAccessTokenId,
                'device_uuid'              => $headers->deviceUuid,
                'platform'                 => $headers->deviceUuid ? $headers->platform : null,
                'app_version'              => $headers->appVersion,
                'operation'                => MobileOfflineOperation::OPERATION_ROLLBACK,
                'kind'                     => $operation->kind,
                'subject_type'             => $operation->subject_type,
                'subject_id'               => $operation->subject_id,
                'parent_type'              => $operation->parent_type,
                'parent_id'                => $operation->parent_id,
                'touched_rows'             => $operation->touched_rows,
                'payload'                  => array_filter(['reason' => $reason]),
                'performed_at'             => now(),
                'synced_at'                => now(),
                'state'                    => MobileOfflineOperation::STATE_APPLIED,
                'rollback_of_id'           => $operation->id,
            ]);

            $effects = [];
            foreach ($before as $id => $was) {
                $now = $after[$id] ?? null;
                if ($now === null) {
                    $effects[] = ['model_type' => 'LocationOrgStock', 'model_id' => $id, 'effect' => 'deleted', 'before' => $was, 'after' => null];
                } elseif ($now !== $was) {
                    $effects[] = ['model_type' => 'LocationOrgStock', 'model_id' => $id, 'effect' => 'updated', 'before' => $was, 'after' => $now];
                }
            }
            $this->storeEffects($rollbackRow, $effects);

            $operation->update([
                'state'                  => MobileOfflineOperation::STATE_ROLLED_BACK,
                'rolled_back_by_user_id' => $by->id,
                'rolled_back_at'         => now(),
                'rollback_reason'        => $reason,
            ]);

            return $operation;
        });
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array{version: int, quantity: float}>
     */
    public static function locationRows(array $ids, bool $lock = false): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('location_org_stocks')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'version', 'quantity'])
            ->mapWithKeys(fn (object $row) => [(int) $row->id => ['version' => (int) $row->version, 'quantity' => (float) $row->quantity]])
            ->all();
    }

    public function handler(string $operation): ?MobileOfflineHandler
    {
        return in_array($operation, $this->registry->operations(), true) ? $this->registry->for($operation) : null;
    }

    private function lockDevice(string $deviceUuid, int $userId): MobileOfflineDevice
    {
        return MobileOfflineDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function run(MobileOfflineOperation $operation, bool $checkBlocking = true, bool $keepCount = false): void
    {
        $this->resetOutcome($operation);
        $handler = $this->handler($operation->operation);

        if ($handler === null) {
            $this->storeInvalid($operation, __('Unknown action :operation.', ['operation' => $operation->operation]));
            $operation->save();

            return;
        }

        try {
            $payload = $handler->validatePayload($operation->payload ?? []);
        } catch (ValidationException $exception) {
            $this->storeInvalid($operation, collect($exception->errors())->flatten()->first() ?? $exception->getMessage());
            $operation->save();

            return;
        }

        $subject = $handler->subject($payload);
        $operation->fill([
            'kind'               => $handler->kind(),
            'subject_type'       => $subject['subject_type'],
            'subject_id'         => $subject['subject_id'],
            'parent_type'        => $subject['parent_type'] ?? null,
            'parent_id'          => $subject['parent_id'] ?? null,
            'touched_rows'       => array_values($handler->touchedRows($payload)),
            'requested_quantity' => $this->requestedQuantity($payload),
        ]);

        $blocker = $checkBlocking ? $this->blocker($operation) : null;
        if ($blocker) {
            $operation->blocked_by_id = $blocker->id;
            $this->storeAnswer($operation, MobileOfflineOperation::STATE_BLOCKED, null, MobileOfflineOutcome::INSTRUCTION_NONE, __('Waits until action :sequence is dealt with.', ['sequence' => $blocker->device_sequence]));
            $operation->save();

            return;
        }

        $context       = new MobileOfflineSyncContext($operation, $operation->user, $operation->warehouse, $keepCount);
        $locationIds   = $operation->touchedLocationOrgStockIds();
        $staffTaskId   = $operation->staff_task_id;
        $before        = self::locationRows($locationIds, lock: true);

        try {
            $outcome = DB::transaction(fn () => $handler->apply($context, $payload));
        } catch (ValidationException $exception) {
            $context->forgetEffects();
            $operation->staff_task_id = $staffTaskId;
            $this->storeInvalid($operation, collect($exception->errors())->flatten()->first() ?? $exception->getMessage());
            $operation->save();

            return;
        }

        $context->mergeLocationRowChanges($before, self::locationRows($locationIds));

        $operation->fill([
            'outcome_reason'      => $outcome->reason,
            'requested_quantity'  => $outcome->requestedQuantity ?? $operation->requested_quantity,
            'applied_quantity'    => $outcome->appliedQuantity,
            'counted_since'       => $outcome->countedSince,
            'conflicting_user_id' => $outcome->conflictingUserId,
        ]);
        $this->storeAnswer($operation, $outcome->state, $outcome->reason, $outcome->instruction, $outcome->message, $outcome->serverSnapshot);
        $operation->save();

        $this->storeEffects($operation, $context->effects());
    }

    private function resetOutcome(MobileOfflineOperation $operation): void
    {
        $operation->fill([
            'outcome_reason'      => null,
            'applied_quantity'    => null,
            'counted_since'       => false,
            'conflicting_user_id' => null,
            'blocked_by_id'       => null,
            'server_snapshot'     => null,
        ]);
    }

    private function storeInvalid(MobileOfflineOperation $operation, string $error): void
    {
        $operation->fill([
            'touched_rows'     => null,
            'applied_quantity' => 0,
        ]);
        $this->storeAnswer($operation, MobileOfflineOperation::STATE_REFUSED, self::REASON_INVALID, MobileOfflineOutcome::INSTRUCTION_NONE, __('This action could not be read: :error', ['error' => $error]));
    }

    /**
     * @param array<string, mixed>|null $rows
     */
    private function storeAnswer(MobileOfflineOperation $operation, string $state, ?string $reason, string $instruction, ?string $message, ?array $rows = null): void
    {
        $operation->state           = $state;
        $operation->outcome_reason  = $reason;
        $operation->server_snapshot = [
            'answer' => ['instruction' => $instruction, 'message' => $message],
            'rows'   => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestedQuantity(array $payload): ?float
    {
        $quantity = Arr::get($payload, 'quantity');

        return is_numeric($quantity) ? (float) $quantity : null;
    }

    private function blocker(MobileOfflineOperation $operation): ?MobileOfflineOperation
    {
        $rows = collect($operation->touched_rows ?? [])
            ->filter(fn (array $row) => isset($row['t'], $row['id']))
            ->map(fn (array $row) => json_encode([['t' => (string) $row['t'], 'id' => (int) $row['id']]]))
            ->unique()
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return MobileOfflineOperation::query()
            ->where('device_uuid', $operation->device_uuid)
            ->where('user_id', $operation->user_id)
            ->whereIn('state', MobileOfflineOperation::BLOCKING_STATES)
            ->where('device_sequence', '<', $operation->device_sequence)
            ->when($operation->exists, fn ($query) => $query->where('id', '!=', $operation->id))
            ->where(function ($query) use ($rows) {
                foreach ($rows as $row) {
                    $query->orWhereRaw('touched_rows @> ?::jsonb', [$row]);
                }
            })
            ->orderBy('device_sequence')
            ->first(['id', 'device_sequence']);
    }

    /**
     * @param array<int, array{model_type: string, model_id: int, effect: string, before: ?array<string, mixed>, after: ?array<string, mixed>}> $effects
     */
    private function storeEffects(MobileOfflineOperation $operation, array $effects): void
    {
        if ($effects === []) {
            return;
        }

        $now = now();
        MobileOfflineOperationEffect::insert(array_map(fn (array $effect) => [
            'mobile_offline_operation_id' => $operation->id,
            'model_type'                  => $effect['model_type'],
            'model_id'                    => $effect['model_id'],
            'effect'                      => $effect['effect'],
            'before'                      => $effect['before'] === null ? null : json_encode($effect['before']),
            'after'                       => $effect['after'] === null ? null : json_encode($effect['after']),
            'created_at'                  => $now,
        ], $effects));
    }
}
