<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inbox;

use App\Models\SysAdmin\User;
use App\Notifications\Work\WorkRecord;
use App\Notifications\WorkNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The mobile Inbox lists only the notifications made for the mobile module, by an allow-list of
 * notification types (.ai/rules/notifications.md). Every other notification stays on the web.
 */
trait WithMobileInbox
{
    public const array MOBILE_INBOX_TYPES = [WorkNotification::class];

    public const int UNREAD_COUNT_CAP = 100;

    protected function inboxQuery(User $user): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->whereIn('type', self::MOBILE_INBOX_TYPES);
    }

    protected function inboxUnreadCount(User $user): int
    {
        return DB::query()
            ->fromSub($this->inboxQuery($user)->whereNull('read_at')->select('id')->limit(self::UNREAD_COUNT_CAP), 'unread_notifications')
            ->count();
    }

    protected function inboxNotification(User $user, string $notificationId): DatabaseNotification
    {
        $notification = DatabaseNotification::query()->findOrFail($notificationId);

        abort_unless(
            $notification->notifiable_type === $user->getMorphClass() && (int) $notification->notifiable_id === (int) $user->id,
            403
        );
        abort_unless(in_array($notification->type, self::MOBILE_INBOX_TYPES, true), 404);

        return $notification;
    }

    /**
     * @param Collection<int, DatabaseNotification> $notifications
     * @return array<int, array<string, mixed>>
     */
    protected function inboxRows(User $user, Collection $notifications): array
    {
        $openable = $this->openableRecords($user, $notifications);

        return $notifications->map(function (DatabaseNotification $notification) use ($openable) {
            $data   = (array) $notification->data;
            $kind   = (string) Arr::get($data, 'kind');
            $record = $openable[$kind][(int) Arr::get($data, 'record.id')] ?? null;

            return [
                'id'         => $notification->id,
                'kind'       => $kind,
                'event'      => Arr::get($data, 'event'),
                'title'      => (string) Arr::get($data, 'title', ''),
                'body'       => (string) Arr::get($data, 'body', ''),
                'created_at' => $notification->created_at?->toIso8601String(),
                'read_at'    => $notification->read_at?->toIso8601String(),
                'target'     => $record ? [
                    'screen'       => $kind,
                    'id'           => (int) $record->getKey(),
                    'reference'    => (string) Arr::get($data, 'record.reference', $record->getAttribute('reference')),
                    'state'        => WorkRecord::state($record),
                    'warehouse_id' => (int) $record->getAttribute('warehouse_id'),
                    'type_group'   => WorkRecord::typeGroup($kind, $record),
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * The records the user may still open, read in one query per kind for the whole page.
     *
     * @param Collection<int, DatabaseNotification> $notifications
     * @return array<string, array<int, Model>>
     */
    private function openableRecords(User $user, Collection $notifications): array
    {
        $idsByKind = [];
        foreach ($notifications as $notification) {
            $kind = Arr::get((array) $notification->data, 'kind');
            if (isset(WorkRecord::MODELS[$kind])) {
                $idsByKind[$kind][] = (int) Arr::get((array) $notification->data, 'record.id');
            }
        }

        if (!$idsByKind) {
            return [];
        }

        $authorisedWarehouseIds = $user->authorisedWarehouses()->pluck('warehouses.id')->map(fn ($id) => (int) $id)->all();

        $openable = [];
        foreach ($idsByKind as $kind => $ids) {
            $openable[$kind] = WorkRecord::query($kind)
                ->with(WorkRecord::relationsToOpen($kind))
                ->whereIn('id', array_unique($ids))
                ->whereIn('warehouse_id', $authorisedWarehouseIds)
                ->get()
                ->filter(fn (Model $record) => WorkRecord::canBeOpenedBy($user, $kind, $record))
                ->keyBy(fn (Model $record) => (int) $record->getKey())
                ->all();
        }

        return $openable;
    }

    /**
     * @return array<string, mixed>
     */
    protected function inboxRow(User $user, DatabaseNotification $notification): array
    {
        return $this->inboxRows($user, collect([$notification]))[0];
    }
}
