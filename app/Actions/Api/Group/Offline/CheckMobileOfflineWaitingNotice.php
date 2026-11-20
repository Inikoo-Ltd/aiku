<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Actions\Tasks\StoreStaffTask;
use App\Models\SysAdmin\MobileOfflineDevice;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckMobileOfflineWaitingNotice
{
    use AsAction;

    public function handle(string $deviceUuid, int $userId, int $senderId, int $count, ?string $oldestPerformedAt, string $noticedAt): ?StaffTask
    {
        $syncedSince = MobileOfflineDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $userId)
            ->where('updated_at', '>=', CarbonImmutable::parse($noticedAt))
            ->exists();

        $absentUser = User::find($userId);
        if ($syncedSince || !$absentUser) {
            return null;
        }

        $sender      = User::find($senderId) ?? $absentUser;
        $lastWork    = MobileOfflineOperation::query()->with('warehouse:id,name,organisation_id')->where('device_uuid', $deviceUuid)->latest('id')->first(['id', 'warehouse_id']);
        $absentName  = $absentUser->contact_name ?? $absentUser->username;
        $description = __(':name left :count actions unsent on a shared phone, the oldest done :oldest. They are sent only when :name signs in again on that phone. Until then the stock and the lines they touched are wrong in Aiku.', [
            'name'   => $absentName,
            'count'  => $count,
            'oldest' => $oldestPerformedAt ? CarbonImmutable::parse($oldestPerformedAt)->toDateTimeString('minute') : __('at an unknown time'),
        ]);

        if ($lastWork?->warehouse) {
            $description .= "\n".__('Warehouse: :warehouse', ['warehouse' => $lastWork->warehouse->name]);
        }

        return StoreStaffTask::make()->action($sender, [
            'subject'     => __('Offline actions of :name are waiting on a shared phone', ['name' => $absentName]),
            'description' => $description,
            'department'  => 'warehouse',
            'data'        => array_filter([
                'organisation_id'          => $lastWork?->warehouse?->organisation_id,
                'mobile_offline_device'    => $deviceUuid,
                'mobile_offline_user_id'   => $userId,
            ], fn ($value) => $value !== null),
        ]);
    }
}
