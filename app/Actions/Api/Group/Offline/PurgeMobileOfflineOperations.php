<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Actions\Tasks\StoreStaffTask;
use App\Models\SysAdmin\MobileOfflineOperation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PurgeMobileOfflineOperations
{
    use AsAction;

    public const int KEEP_DAYS = 7;

    public const int DEVICE_KEEP_DAYS = 365;

    public const int BATCH = 5000;

    public string $commandSignature = 'mobile-offline:purge';

    public string $commandDescription = 'Expire offline actions left open for 7 days and delete old offline journal rows and devices';

    /**
     * @return array{expired: int, deleted: int, devices: int}
     */
    public function handle(): array
    {
        $expired = $this->expireOpenActions();

        $cutoff  = now()->subDays(self::KEEP_DAYS);
        $deleted = $this->deleteInBatches('mobile_offline_operations', fn ($query) => $query->whereIn('state', MobileOfflineOperation::CLOSED_STATES)->where('synced_at', '<', $cutoff))
            + $this->deleteInBatches('mobile_offline_operations', fn ($query) => $query->where('state', MobileOfflineOperation::STATE_EXPIRED)->where('expired_at', '<', $cutoff));

        $devices = $this->deleteInBatches('mobile_offline_devices', fn ($query) => $query->where('updated_at', '<', now()->subDays(self::DEVICE_KEEP_DAYS)));

        return ['expired' => $expired, 'deleted' => $deleted, 'devices' => $devices];
    }

    private function expireOpenActions(): int
    {
        $expiredAt = now();
        $ids       = MobileOfflineOperation::query()
            ->whereIn('state', MobileOfflineOperation::OPEN_STATES)
            ->where('synced_at', '<', $expiredAt->copy()->subDays(self::KEEP_DAYS))
            ->pluck('id');

        $allIds = collect();
        while ($ids->isNotEmpty()) {
            $allIds = $allIds->merge($ids);
            foreach ($ids->chunk(self::BATCH) as $chunk) {
                MobileOfflineOperation::query()->whereIn('id', $chunk)->update(['state' => MobileOfflineOperation::STATE_EXPIRED, 'expired_at' => $expiredAt]);
            }

            $ids = MobileOfflineOperation::query()
                ->where('state', MobileOfflineOperation::STATE_BLOCKED)
                ->whereIn('blocked_by_id', $ids)
                ->pluck('id');
        }

        if ($allIds->isEmpty()) {
            return 0;
        }

        foreach ($allIds->chunk(self::BATCH) as $chunk) {
            $this->askSupervisors(MobileOfflineOperation::query()->with(['user', 'warehouse'])->whereIn('id', $chunk)->orderBy('device_sequence')->get());
        }

        return $allIds->count();
    }

    /**
     * @param Collection<int, MobileOfflineOperation> $operations
     */
    private function askSupervisors(Collection $operations): void
    {
        foreach ($operations->groupBy(fn (MobileOfflineOperation $operation) => $operation->warehouse_id.':'.$operation->user_id) as $group) {
            /** @var MobileOfflineOperation $first */
            $first = $group->first();
            if (!$first->user || !$first->warehouse) {
                continue;
            }

            $name = $first->user->contact_name ?? $first->user->username;
            StoreStaffTask::make()->action($first->user, [
                'subject'     => __(':count offline actions of :name expired unresolved', ['count' => $group->count(), 'name' => $name]),
                'description' => __('These offline actions were still open after :days days, so Aiku and the shelves may disagree. Please check them:', ['days' => self::KEEP_DAYS])
                    ."\n".$group->map(fn (MobileOfflineOperation $operation) => '#'.$operation->device_sequence.' '.$operation->operation.' ('.($operation->outcome_reason ?? ($operation->blocked_by_id ? 'blocked' : 'open')).')'.($operation->subject_type ? ' '.$operation->subject_type.' '.$operation->subject_id : ''))->implode("\n")
                    ."\n".__('Warehouse: :warehouse', ['warehouse' => $first->warehouse->name]),
                'department'  => 'warehouse',
                'data'        => [
                    'organisation_id'                 => $first->warehouse->organisation_id,
                    'mobile_offline_operation_ids'    => $group->pluck('id')->all(),
                ],
            ]);
        }
    }

    private function deleteInBatches(string $table, callable $scope): int
    {
        $deleted = 0;
        do {
            $batch = DB::table($table)->whereIn('id', $scope(DB::table($table)->select('id'))->limit(self::BATCH))->delete();
            $deleted += $batch;
        } while ($batch === self::BATCH);

        return $deleted;
    }

    public function asCommand(Command $command): int
    {
        $result = $this->handle();
        $command->info(sprintf('%d expired, %d deleted, %d devices removed', $result['expired'], $result['deleted'], $result['devices']));

        return 0;
    }
}
