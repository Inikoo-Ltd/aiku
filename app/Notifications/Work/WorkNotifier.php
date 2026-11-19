<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Notifications\Work;

use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\User;
use App\Notifications\WorkNotification;
use ArrayObject;
use Closure;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * Turns changes on delivery notes, picking sessions, customer returns and pallet returns into the
 * mobile work notifications. Model hooks and a few explicit calls record what happened; the
 * notices go out once the outermost transaction commits (or at once outside one), merged per
 * record and recipient, without the actor, and "modified" only once per assignment.
 */
class WorkNotifier
{
    public const array EVENT_PRIORITY = [
        'modified'    => 1,
        'ready_again' => 2,
        'assigned'    => 3,
        'unassigned'  => 4,
        'taken_over'  => 5,
        'cancelled'   => 6,
    ];

    public const int MODIFIED_NOTICE_DAYS = 30;

    private static int $pausedDeliveryNotePickerNotices = 0;

    /** @var WeakMap<DatabaseTransactionRecord, ArrayObject<int, array<string, mixed>>>|null */
    private static ?WeakMap $signalsByTransaction = null;

    public static function recordUpdated(Model $record): void
    {
        rescue(fn () => self::detectChanges($record));
    }

    public static function recordDeleted(Model $record): void
    {
        rescue(function () use ($record) {
            if (WorkRecord::kindOf($record) !== 'pallet_return' || WorkRecord::state($record) === WorkRecord::CANCELLED_STATES['pallet_return']) {
                return;
            }

            $snapshot = WorkRecord::snapshot('pallet_return', $record);
            $signals  = array_map(
                fn (int $recipientId) => self::notice('pallet_return', $record, $recipientId, 'cancelled', $snapshot),
                WorkRecord::assignees('pallet_return', $snapshot['state'], $record->getAttributes())
            );

            self::pushAll($signals);
        });
    }

    /**
     * Lines, delivery address or notes changed by somebody other than the assignee, through a path
     * the model hooks do not see (quiet updates, pivots, line tables).
     */
    public static function modified(?Model $record, string $change): void
    {
        if ($record) {
            rescue(fn () => self::pushAll([self::forCurrentAssignees($record, 'modified', [$change])]));
        }
    }

    /**
     * An unlock or temporary claim: the assignee columns stay as they are, but somebody else now
     * works on the record.
     */
    public static function takenOver(Model $record): void
    {
        rescue(fn () => self::pushAll([self::forCurrentAssignees($record, 'taken_over', [])]));
    }

    /**
     * A picking session handed to another picker moves its notes along; the session notice is the
     * only one the pickers get for it.
     */
    public static function withoutDeliveryNotePickerNotices(Closure $callback): mixed
    {
        self::$pausedDeliveryNotePickerNotices++;

        try {
            return $callback();
        } finally {
            self::$pausedDeliveryNotePickerNotices--;
        }
    }

    public static function modifiedKey(string $kind, int $recordId, int $userId): string
    {
        return "work-modified:$kind:$recordId:$userId";
    }

    private static function detectChanges(Model $record): void
    {
        $kind = WorkRecord::kindOf($record);
        if ($kind === null) {
            return;
        }

        $attributes     = $record->getAttributes();
        $original       = $record->getRawOriginal();
        $oldState       = (string) ($original['state'] ?? '');
        $newState       = (string) ($attributes['state'] ?? '');
        $cancelledState = WorkRecord::CANCELLED_STATES[$kind] ?? null;

        if ($cancelledState !== null && $newState === $cancelledState && $oldState !== $cancelledState) {
            $snapshot = WorkRecord::snapshot($kind, $record, reference: (string) ($original['reference'] ?? $attributes['reference'] ?? ''), state: $newState);
            self::pushAll(array_map(
                fn (int $recipientId) => self::notice($kind, $record, $recipientId, 'cancelled', $snapshot),
                WorkRecord::assignees($kind, $oldState, $original)
            ));

            return;
        }

        $snapshot = WorkRecord::snapshot($kind, $record, state: $newState);
        $signals  = self::assigneeSignals($kind, $record, $attributes, $original, $oldState, $newState, $snapshot);

        if (self::isReadyAgain($kind, $record, $attributes, $original, $oldState, $newState)) {
            foreach (WorkRecord::assignees($kind, $newState, $attributes) as $recipientId) {
                $signals[] = self::notice($kind, $record, $recipientId, 'ready_again', $snapshot);
            }
        }

        $changes = array_keys(array_filter([
            'notes'   => isset(WorkRecord::NOTE_COLUMNS[$kind]) && $record->wasChanged(WorkRecord::NOTE_COLUMNS[$kind]),
            'address' => isset(WorkRecord::ADDRESS_COLUMNS[$kind]) && $record->wasChanged(WorkRecord::ADDRESS_COLUMNS[$kind]),
        ]));
        if ($changes) {
            $signals[] = self::forCurrentAssignees($record, 'modified', $changes);
        }

        self::pushAll($signals);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $original
     * @param array<string, mixed> $snapshot
     * @return array<int, array<string, mixed>>
     */
    private static function assigneeSignals(string $kind, Model $record, array $attributes, array $original, string $oldState, string $newState, array $snapshot): array
    {
        $signals = [];
        foreach (WorkRecord::ASSIGNEE_COLUMNS[$kind] as $column) {
            if (!$record->wasChanged($column) || ($kind === 'delivery_note' && $column === 'picker_user_id' && self::$pausedDeliveryNotePickerNotices > 0)) {
                continue;
            }

            $oldUserId = (int) ($original[$column] ?? 0);
            $newUserId = (int) ($attributes[$column] ?? 0);

            if ($newUserId) {
                $signals[] = ['type' => 'reassigned', 'kind' => $kind, 'id' => (int) $record->getKey(), 'user' => $newUserId];
                if (in_array($column, WorkRecord::assigneeColumns($kind, $newState, $attributes), true)) {
                    $signals[] = self::notice($kind, $record, $newUserId, 'assigned', $snapshot);
                }
            }

            if ($oldUserId && in_array($column, WorkRecord::assigneeColumns($kind, $oldState, $original), true)) {
                $signals[] = self::notice($kind, $record, $oldUserId, $newUserId ? 'taken_over' : 'unassigned', $snapshot, $newUserId ?: null);
            }
        }

        return $signals;
    }

    /**
     * A note inside a picking session is worked through its session, so only the session says it
     * can go ahead, the same way a session reassignment notifies per session only.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $original
     */
    private static function isReadyAgain(string $kind, Model $record, array $attributes, array $original, string $oldState, string $newState): bool
    {
        $waitingState   = WorkRecord::WAITING_STATES[$kind] ?? null;
        $cancelledState = WorkRecord::CANCELLED_STATES[$kind] ?? null;

        if ($waitingState !== null && $oldState === $waitingState && $newState !== $waitingState && $newState !== $cancelledState) {
            return $kind !== 'delivery_note' || !$record->pickingSessions()->exists();
        }

        if ($kind === 'picking_session') {
            return $record->wasChanged('is_waiting_ready') && !($original['is_waiting_ready'] ?? false) && $attributes['is_waiting_ready'];
        }

        if ($kind === 'pallet_return') {
            return $record->wasChanged('number_items_waiting_crm')
                && (int) ($original['number_items_waiting_crm'] ?? 0) > 0
                && (int) ($attributes['number_items_waiting_crm'] ?? 0) === 0
                && $newState !== $cancelledState;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private static function notice(string $kind, Model $record, int $recipientId, string $event, array $snapshot, ?int $holderId = null): array
    {
        return [
            'type'        => 'notice',
            'kind'        => $kind,
            'id'          => (int) $record->getKey(),
            'recipient'   => $recipientId,
            'event'       => $event,
            'record'      => $snapshot,
            'changes'     => [],
            'holder'      => $holderId,
            'platform_id' => $record->getAttributes()['platform_id'] ?? null,
        ];
    }

    /**
     * @param array<int, string> $changes
     * @return array<string, mixed>
     */
    private static function forCurrentAssignees(Model $record, string $event, array $changes): array
    {
        return [
            'type'    => 'current',
            'kind'    => WorkRecord::kindOf($record),
            'id'      => (int) $record->getKey(),
            'event'   => $event,
            'changes' => $changes,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     */
    private static function pushAll(array $signals): void
    {
        if (!$signals) {
            return;
        }

        $actor   = self::currentActor();
        $signals = array_map(fn (array $signal) => $signal + ['actor' => $actor], $signals);

        if (DB::transactionLevel() === 0) {
            self::flush($signals);

            return;
        }

        $transaction = self::outermostTransaction();
        if (!$transaction) {
            DB::afterCommit(fn () => self::flush($signals));

            return;
        }

        self::$signalsByTransaction ??= new WeakMap();
        if (!isset(self::$signalsByTransaction[$transaction])) {
            $pending                                  = new ArrayObject();
            self::$signalsByTransaction[$transaction] = $pending;
            $transaction->addCallback(fn () => self::flush($pending->getArrayCopy()));
        }

        foreach ($signals as $signal) {
            self::$signalsByTransaction[$transaction]->append($signal);
        }
    }

    private static function outermostTransaction(): ?DatabaseTransactionRecord
    {
        $connectionName = DB::connection()->getName();

        return app('db.transactions')->getPendingTransactions()
            ->last(fn (DatabaseTransactionRecord $transaction) => $transaction->connection === $connectionName && $transaction->level === 1);
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     */
    private static function flush(array $signals): void
    {
        rescue(function () use ($signals) {
            foreach ($signals as $signal) {
                if ($signal['type'] === 'reassigned') {
                    Cache::forget(self::modifiedKey($signal['kind'], $signal['id'], $signal['user']));
                }
            }

            $notices = array_merge(
                array_values(array_filter($signals, fn (array $signal) => $signal['type'] === 'notice')),
                self::noticesForCurrentAssignees(array_values(array_filter($signals, fn (array $signal) => $signal['type'] === 'current')))
            );

            $merged = [];
            foreach ($notices as $notice) {
                if ($notice['actor']['type'] === 'user' && $notice['actor']['id'] === $notice['recipient']) {
                    continue;
                }

                $key      = $notice['kind'].':'.$notice['id'].':'.$notice['recipient'];
                $existing = $merged[$key] ?? null;
                if ($existing && $existing['event'] === 'modified' && $notice['event'] === 'modified') {
                    $notice['changes'] = array_values(array_unique(array_merge($existing['changes'], $notice['changes'])));
                }
                if (!$existing || self::EVENT_PRIORITY[$notice['event']] >= self::EVENT_PRIORITY[$existing['event']]) {
                    $merged[$key] = $notice;
                }
            }

            $toSend = array_filter(
                $merged,
                fn (array $notice) => $notice['event'] !== 'modified'
                    || Cache::add(self::modifiedKey($notice['kind'], $notice['id'], $notice['recipient']), true, now()->addDays(self::MODIFIED_NOTICE_DAYS))
            );

            if ($toSend) {
                self::send(array_values($toSend));
            }
        });
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     * @return array<int, array<string, mixed>>
     */
    private static function noticesForCurrentAssignees(array $signals): array
    {
        $notices = [];
        foreach (collect($signals)->groupBy(fn (array $signal) => $signal['kind'].':'.$signal['id']) as $group) {
            $first  = $group->first();
            $record = WorkRecord::query($first['kind'])->find($first['id']);
            if (!$record) {
                continue;
            }

            $snapshot = WorkRecord::snapshot($first['kind'], $record);
            foreach (WorkRecord::assignees($first['kind'], $snapshot['state'], $record->getAttributes()) as $recipientId) {
                foreach ($group as $signal) {
                    $notices[] = array_merge(self::notice($first['kind'], $record, $recipientId, $signal['event'], $snapshot), [
                        'changes' => $signal['changes'],
                        'actor'   => $signal['actor'],
                    ]);
                }
            }
        }

        return $notices;
    }

    /**
     * @param array<int, array<string, mixed>> $notices
     */
    private static function send(array $notices): void
    {
        $userIds = array_unique(array_merge(array_column($notices, 'recipient'), array_filter(array_column($notices, 'holder'))));
        $users   = User::query()->with('language:id,code')->whereIn('id', $userIds)->get()->keyBy('id');

        $platformIds   = array_unique(array_filter(array_column($notices, 'platform_id')));
        $platformNames = $platformIds ? Platform::query()->whereIn('id', $platformIds)->pluck('name', 'id') : collect();

        foreach ($notices as $notice) {
            $recipient = $users->get($notice['recipient']);
            if (!$recipient || !$recipient->status || !$notice['record']['warehouse_id']) {
                continue;
            }

            $holder = $notice['holder'] ? $users->get($notice['holder']) : null;

            $recipient->notify(WorkNotification::for(
                recipient: $recipient,
                kind: $notice['kind'],
                event: $notice['event'],
                record: $notice['record'],
                actor: self::displayedActor($notice['actor'], $notice['platform_id'], $platformNames->get($notice['platform_id'])),
                changes: $notice['changes'],
                holderName: $holder ? self::userName($holder) : null,
            ));
        }
    }

    /**
     * @param array{type: string, id: int|null, name: string|null} $actor
     * @return array{type: string, id: int|null, name: string|null}
     */
    private static function displayedActor(array $actor, ?int $platformId, ?string $platformName): array
    {
        if ($actor['type'] !== 'system') {
            return $actor;
        }

        return $platformName
            ? ['type' => 'platform', 'id' => $platformId, 'name' => $platformName]
            : ['type' => 'system', 'id' => null, 'name' => 'Aiku'];
    }

    /**
     * @return array{type: string, id: int|null, name: string|null}
     */
    private static function currentActor(): array
    {
        $authenticated = rescue(fn () => auth()->user(), report: false);

        if ($authenticated instanceof User) {
            return self::userActor($authenticated);
        }
        if ($authenticated instanceof WebUser) {
            return self::customerActor($authenticated->customer_id);
        }
        if ($authenticated instanceof Customer) {
            return self::customerActor($authenticated->id);
        }

        $auditUser = Context::getHidden('audit_user');
        if (is_array($auditUser) && count($auditUser) === 2) {
            [$class, $id] = $auditUser;
            if ($class === User::class && $user = User::find($id)) {
                return self::userActor($user);
            }
            if ($class === WebUser::class) {
                return self::customerActor(WebUser::whereKey($id)->value('customer_id'));
            }
        }

        return ['type' => 'system', 'id' => null, 'name' => null];
    }

    /**
     * @return array{type: string, id: int, name: string}
     */
    private static function userActor(User $user): array
    {
        return ['type' => 'user', 'id' => $user->id, 'name' => self::userName($user)];
    }

    /**
     * @return array{type: string, id: int|null, name: string|null}
     */
    private static function customerActor(?int $customerId): array
    {
        return [
            'type' => 'customer',
            'id'   => $customerId,
            'name' => $customerId ? Customer::whereKey($customerId)->value('name') : null,
        ];
    }

    private static function userName(User $user): string
    {
        return $user->contact_name ?: $user->username;
    }
}
