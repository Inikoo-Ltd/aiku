<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Tasks\StoreStaffTask;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\MobileOfflineOperationEffect;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MobileOfflineSyncContext implements MobileOfflineContext
{
    /**
     * @var array<int, array{model_type: string, model_id: int, effect: string, before: ?array<string, mixed>, after: ?array<string, mixed>}>
     */
    private array $effects = [];

    private array $stockCheckKeys = [];

    public function __construct(
        private readonly MobileOfflineOperation $operation,
        private readonly User $user,
        private readonly Warehouse $warehouse,
        private readonly bool $keepCount = false,
    ) {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function warehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function organisation(): Organisation
    {
        return $this->warehouse->organisation;
    }

    public function performedAt(): CarbonImmutable
    {
        return $this->operation->performed_at ?? $this->operation->synced_at;
    }

    public function seenAt(): ?CarbonImmutable
    {
        return $this->operation->seen_at;
    }

    public function countedSince(LocationOrgStock $locationOrgStock): bool
    {
        $auditedAt = $locationOrgStock->audited_at;

        return $auditedAt !== null && Carbon::parse($auditedAt)->greaterThan($this->performedAt());
    }

    public function ownChainAllows(LocationOrgStock $locationOrgStock, int $baseVersion): bool
    {
        if ($this->keepCount) {
            return true;
        }

        $currentVersion = (int) DB::table('location_org_stocks')->where('id', $locationOrgStock->id)->value('version');

        $ownEffects = MobileOfflineOperationEffect::query()
            ->join('mobile_offline_operations', 'mobile_offline_operations.id', '=', 'mobile_offline_operation_effects.mobile_offline_operation_id')
            ->where('mobile_offline_operations.device_uuid', $this->operation->device_uuid)
            ->where('mobile_offline_operations.user_id', $this->operation->user_id)
            ->when($this->operation->exists, fn ($query) => $query->where('mobile_offline_operations.id', '!=', $this->operation->id))
            ->where('mobile_offline_operation_effects.model_type', 'LocationOrgStock')
            ->where('mobile_offline_operation_effects.model_id', $locationOrgStock->id)
            ->where('mobile_offline_operation_effects.effect', 'updated')
            ->orderBy('mobile_offline_operation_effects.id')
            ->get(['mobile_offline_operation_effects.before', 'mobile_offline_operation_effects.after']);

        $point = $baseVersion;
        foreach ($ownEffects as $effect) {
            $before = $effect->before['version'] ?? null;
            $after  = $effect->after['version'] ?? null;
            if ($before === null || $after === null || (int) $before < $baseVersion) {
                continue;
            }
            if ((int) $before !== $point) {
                return false;
            }
            $point = (int) $after;
        }

        return $point === $currentVersion;
    }

    public function recordCreated(Model $model): void
    {
        $this->effects[] = [
            'model_type' => class_basename($model),
            'model_id'   => (int) $model->getKey(),
            'effect'     => 'created',
            'before'     => null,
            'after'      => null,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function recordUpdated(Model $model, array $before, array $after): void
    {
        $this->effects[] = [
            'model_type' => class_basename($model),
            'model_id'   => (int) $model->getKey(),
            'effect'     => 'updated',
            'before'     => $before,
            'after'      => $after,
        ];
    }

    /**
     * @param array<string, mixed> $before
     */
    public function recordDeleted(Model $model, array $before): void
    {
        $this->effects[] = [
            'model_type' => class_basename($model),
            'model_id'   => (int) $model->getKey(),
            'effect'     => 'deleted',
            'before'     => $before,
            'after'      => null,
        ];
    }

    public function requestStockCheck(Model $model, string $description): void
    {
        $key = class_basename($model).':'.$model->getKey();
        if ($this->operation->staff_task_id || in_array($key, $this->stockCheckKeys, true)) {
            return;
        }
        $this->stockCheckKeys[] = $key;

        [$modelType, $modelId] = $this->taskModel($model);

        $task = StoreStaffTask::make()->action($this->user, array_filter([
            'subject'     => __('Stock check: :subject', ['subject' => $this->modelLabel($model)]),
            'description' => $description."\n".__('Warehouse: :warehouse', ['warehouse' => $this->warehouse->name]),
            'department'  => 'warehouse',
            'model_type'  => $modelType,
            'model_id'    => $modelId,
            'data'        => [
                'organisation_id'              => $this->warehouse->organisation_id,
                'warehouse_id'                 => $this->warehouse->id,
                'mobile_offline_model_type'    => class_basename($model),
                'mobile_offline_model_id'      => (int) $model->getKey(),
            ],
        ], fn ($value) => $value !== null));

        $this->operation->staff_task_id = $task->id;
    }

    /**
     * @return array<int, array{model_type: string, model_id: int, effect: string, before: ?array<string, mixed>, after: ?array<string, mixed>}>
     */
    public function effects(): array
    {
        return $this->effects;
    }

    public function forgetEffects(): void
    {
        $this->effects        = [];
        $this->stockCheckKeys = [];
    }

    /**
     * @param array<int, array{version: int, quantity: float}> $before
     * @param array<int, array{version: int, quantity: float}> $after
     */
    public function mergeLocationRowChanges(array $before, array $after): void
    {
        foreach ($before as $id => $was) {
            $now = $after[$id] ?? null;
            if ($now === null || ($now['version'] === $was['version'] && abs($now['quantity'] - $was['quantity']) < 0.000001)) {
                continue;
            }

            $index = collect($this->effects)->search(fn (array $effect) => $effect['model_type'] === 'LocationOrgStock' && $effect['model_id'] === $id && $effect['effect'] === 'updated');
            if ($index === false) {
                $this->effects[] = [
                    'model_type' => 'LocationOrgStock',
                    'model_id'   => $id,
                    'effect'     => 'updated',
                    'before'     => $was,
                    'after'      => $now,
                ];
                continue;
            }

            $this->effects[$index]['before'] = [...($this->effects[$index]['before'] ?? []), ...$was];
            $this->effects[$index]['after']  = [...($this->effects[$index]['after'] ?? []), ...$now];
        }

        $createdIds = collect($this->effects)
            ->filter(fn (array $effect) => $effect['model_type'] === 'LocationOrgStock' && $effect['effect'] === 'created')
            ->pluck('model_id')
            ->all();
        if ($createdIds === []) {
            return;
        }

        $rows = MobileOfflineEngine::locationRows($createdIds);
        foreach ($this->effects as $index => $effect) {
            if ($effect['model_type'] === 'LocationOrgStock' && $effect['effect'] === 'created' && isset($rows[$effect['model_id']])) {
                $this->effects[$index]['after'] = $rows[$effect['model_id']];
            }
        }
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function taskModel(Model $model): array
    {
        return match (true) {
            $model instanceof LocationOrgStock                                     => ['Location', (int) $model->location_id],
            $model instanceof DeliveryNoteItem                                     => ['DeliveryNote', (int) $model->delivery_note_id],
            in_array(class_basename($model), StaffTask::LINKABLE_MODELS, true) => [class_basename($model), (int) $model->getKey()],
            default                                                                => [null, null],
        };
    }

    private function modelLabel(Model $model): string
    {
        return match (true) {
            $model instanceof LocationOrgStock => __(':sko at :location', [
                'sko'      => $model->orgStock?->code,
                'location' => $model->location?->code,
            ]),
            default => (string) ($model->getAttribute('code') ?? $model->getAttribute('reference') ?? class_basename($model).' '.$model->getKey()),
        };
    }
}
