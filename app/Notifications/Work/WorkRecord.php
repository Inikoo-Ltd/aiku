<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Notifications\Work;

use App\Actions\Api\Group\GetMobileWarehouses;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The four records a warehouse worker is assigned to, as the mobile work notifications see them:
 * who is working on each in which state (the My work definition), and who may open it on the app.
 */
class WorkRecord
{
    public const array MODELS = [
        'delivery_note'   => DeliveryNote::class,
        'picking_session' => PickingSession::class,
        'return'          => ReturnDeliveryNote::class,
        'pallet_return'   => PalletReturn::class,
    ];

    public const array ASSIGNEE_COLUMNS = [
        'delivery_note'   => ['picker_user_id', 'packer_user_id'],
        'picking_session' => ['user_id'],
        'return'          => ['handler_user_id'],
        'pallet_return'   => ['picker_user_id', 'packer_user_id'],
    ];

    public const array CANCELLED_STATES = [
        'delivery_note' => 'cancelled',
        'return'        => 'cancelled',
        'pallet_return' => 'cancel',
    ];

    public const array WAITING_STATES = [
        'delivery_note'   => 'handling_blocked',
        'picking_session' => 'handling_blocked',
    ];

    public const array NOTE_COLUMNS = [
        'delivery_note' => ['customer_notes', 'public_notes', 'internal_notes', 'shipping_notes', 'private_warehouse_note'],
        'pallet_return' => ['customer_notes', 'public_notes', 'internal_notes', 'collection_notes'],
    ];

    public const array ADDRESS_COLUMNS = [
        'delivery_note' => ['contact_name', 'company_name', 'email', 'phone'],
        'pallet_return' => ['delivery_address_id'],
    ];

    public static function kindOf(Model $model): ?string
    {
        $kind = array_search($model::class, self::MODELS, true);

        return $kind === false ? null : $kind;
    }

    public static function query(string $kind, bool $withTrashed = false): Builder
    {
        $modelClass = self::MODELS[$kind];
        $query      = $modelClass::query();

        if ($withTrashed && in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    public static function assigneeColumns(string $kind, string $state, array $attributes): array
    {
        return match ($kind) {
            'delivery_note'   => match ($state) {
                'queued', 'handling', 'handling_blocked' => ['picker_user_id'],
                'picked'                                 => [empty($attributes['packer_user_id']) ? 'picker_user_id' : 'packer_user_id'],
                'packing'                                => ['packer_user_id'],
                default                                  => [],
            },
            'picking_session' => in_array($state, ['in_process', 'handling', 'handling_blocked'], true) ? ['user_id'] : [],
            'return'          => in_array($state, ['received', 'returning'], true) ? ['handler_user_id'] : [],
            'pallet_return'   => match ($state) {
                'picking' => ['picker_user_id'],
                'picked'  => ['packer_user_id'],
                default   => [],
            },
        };
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<int, int>
     */
    public static function assignees(string $kind, string $state, array $attributes): array
    {
        return collect(self::assigneeColumns($kind, $state, $attributes))
            ->map(fn (string $column) => (int) ($attributes[$column] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function state(Model $record): string
    {
        $state = $record->getAttribute('state');

        return $state instanceof BackedEnum ? (string) $state->value : (string) $state;
    }

    /**
     * @return array{type: string, id: int, reference: string, warehouse_id: int|null, state: string, type_group: string|null}
     */
    public static function snapshot(string $kind, Model $record, ?string $reference = null, ?string $state = null): array
    {
        return [
            'type'         => $kind,
            'id'           => (int) $record->getKey(),
            'reference'    => $reference ?? (string) $record->getAttribute('reference'),
            'warehouse_id' => $record->getAttribute('warehouse_id') ? (int) $record->getAttribute('warehouse_id') : null,
            'state'        => $state ?? self::state($record),
            'type_group'   => self::typeGroup($kind, $record),
        ];
    }

    public static function typeGroup(string $kind, Model $record): ?string
    {
        if ($kind !== 'pallet_return') {
            return null;
        }

        $type = $record->getAttribute('type');

        return ($type instanceof BackedEnum ? $type->value : $type) === PalletReturnTypeEnum::PALLET->value ? 'pallets' : 'items';
    }

    public static function canBeNotifiedTo(User $user, string $kind, int $recordId): bool
    {
        $record = self::query($kind, withTrashed: true)->find($recordId);

        return $record
            && $user->status
            && $record->getAttribute('warehouse_id')
            && $user->authorisedWarehouses()->whereKey($record->getAttribute('warehouse_id'))->exists()
            && self::canBeOpenedBy($user, $kind, $record);
    }

    /**
     * The check each mobile screen runs before it opens the record (the shared module checks of
     * WithMobileModuleAccess, and DeliveryNote::canBeViewedBy for a note), minus the authorised
     * warehouse, which the caller checks once for many records.
     */
    public static function canBeOpenedBy(User $user, string $kind, Model $record): bool
    {
        if ((int) $record->getAttribute('group_id') !== (int) $user->group_id || !$record->getAttribute('warehouse_id')) {
            return false;
        }

        return match ($kind) {
            'delivery_note'   => $record->canBeViewedBy($user),
            'picking_session' => $record->type !== PickingSessionTypeEnum::FULFILMENT && GetMobileWarehouses::canViewGoodsOut($user, $record->warehouse),
            'return'          => GetMobileWarehouses::canViewReturns($user, $record->warehouse),
            'pallet_return'   => GetMobileWarehouses::canViewFulfilment($user, $record->warehouse),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function relationsToOpen(string $kind): array
    {
        return $kind === 'delivery_note' ? ['shop.fulfilment'] : ['warehouse'];
    }
}
