<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditScopeEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\JsonResponse;

trait WithMobileStoredItemAudits
{
    use WithMobileFulfilmentOutbound;

    public static function canAuditStoredItems(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "fulfilment.$warehouse->id.edit",
            "supervisor-fulfilment.$warehouse->id",
        ]);
    }

    protected function ensureCanAuditStoredItems(User $user, Warehouse $warehouse): void
    {
        $this->ensureFulfilmentAccess($user, $warehouse, self::canAuditStoredItems($user, $warehouse), __('You can not audit stored items in this warehouse.'));
    }

    protected function palletOfAudit(Warehouse $warehouse, StoredItemAudit $storedItemAudit): Pallet
    {
        abort_unless($storedItemAudit->scope_type === StoredItemAuditScopeEnum::PALLET->value && $storedItemAudit->scope_id, 404);

        $pallet = Pallet::find($storedItemAudit->scope_id);
        abort_unless($pallet && $pallet->warehouse_id === $warehouse->id, 404);

        return $pallet;
    }

    protected function changeAudit(StoredItemAudit $storedItemAudit, Closure $change): mixed
    {
        return $this->underLock('stored_item_audit', $storedItemAudit->id, function () use ($storedItemAudit, $change) {
            $storedItemAudit = StoredItemAudit::findOrFail($storedItemAudit->id);

            if ($storedItemAudit->state !== StoredItemAuditStateEnum::IN_PROCESS) {
                $this->refuseState(__('This audit is already completed.'));
            }

            return $change($storedItemAudit);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function auditLines(Pallet $pallet, StoredItemAudit $storedItemAudit): array
    {
        $currentItems = $pallet->getEditStoredItemDeltasQuery($pallet->id, $storedItemAudit->id)
            ->where('pallet_stored_items.pallet_id', $pallet->id)
            ->get()
            ->map(fn (object $row) => [
                'stored_item_id'   => (int) $row->stored_item_id,
                'quantity'         => (int) $row->quantity,
                'audited_quantity' => $row->stored_item_audit_delta_id ? (int) $row->audited_quantity : null,
                'delta_id'         => $row->stored_item_audit_delta_id ? (int) $row->stored_item_audit_delta_id : null,
                'audit_type'       => $row->stored_item_audit_delta_id ? $row->audit_type : null,
                'is_new_item'      => false,
            ]);

        $newItems = $pallet->getEditNewStoredItemDeltasQuery($pallet->id)
            ->where('stored_item_audit_deltas.pallet_id', $pallet->id)
            ->where('stored_item_audit_deltas.stored_item_audit_id', $storedItemAudit->id)
            ->get()
            ->map(fn (object $row) => [
                'stored_item_id'   => (int) $row->stored_item_id,
                'quantity'         => 0,
                'audited_quantity' => (int) $row->audited_quantity,
                'delta_id'         => (int) $row->audit_id,
                'audit_type'       => $row->audit_type,
                'is_new_item'      => true,
            ]);

        $lines       = $currentItems->concat($newItems);
        $storedItems = StoredItem::findMany($lines->pluck('stored_item_id')->unique()->values())->keyBy('id');
        $typeLabels  = StoredItemAuditDeltaTypeEnum::labels();

        return $lines->map(function (array $line) use ($storedItems, $typeLabels) {
            $storedItem = $storedItems->get($line['stored_item_id']);

            return [
                'stored_item'      => [
                    'id'        => $line['stored_item_id'],
                    'reference' => $storedItem?->reference,
                    'name'      => $storedItem?->name,
                    'barcode'   => $storedItem?->barcode,
                ],
                'quantity'         => $line['quantity'],
                'audited_quantity' => $line['audited_quantity'],
                'delta_id'         => $line['delta_id'],
                'audit_type'       => $line['audit_type'],
                'audit_type_label' => $line['audit_type'] ? ($typeLabels[$line['audit_type']] ?? null) : null,
                'is_new_item'      => $line['is_new_item'],
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditDetail(StoredItemAudit $storedItemAudit, Pallet $pallet): array
    {
        $pallet->loadMissing(['location', 'fulfilmentCustomer.customer']);
        $lines     = $this->auditLines($pallet, $storedItemAudit);
        $counted   = collect($lines)->filter(fn (array $line) => $line['delta_id'] !== null);
        $inProcess = $storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS;

        return [
            'id'                => $storedItemAudit->id,
            'reference'         => $storedItemAudit->reference,
            'state'             => $storedItemAudit->state->value,
            'state_label'       => StoredItemAuditStateEnum::labels()[$storedItemAudit->state->value],
            'pallet'            => [
                'id'                 => $pallet->id,
                'reference'          => $pallet->reference,
                'customer_reference' => $pallet->customer_reference,
                'location'           => $this->locationReference($pallet->location),
            ],
            'customer'          => $this->customerReference($pallet->fulfilmentCustomer),
            'lines'             => $lines,
            'counts'            => [
                'lines'       => count($lines),
                'counted'     => $counted->count(),
                'differences' => $counted->filter(fn (array $line) => $line['audited_quantity'] !== $line['quantity'])->count(),
            ],
            'available_actions' => $inProcess ? array_values(array_filter([
                $this->actionOption('count', __('Count')),
                $this->actionOption('add_item', __('Add item')),
                $storedItemAudit->deltas()->exists() ? $this->actionOption('complete', __('Complete audit')) : null,
            ])) : [],
        ];
    }

    protected function respondWithAudit(StoredItemAudit $storedItemAudit, Pallet $pallet, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->auditDetail(StoredItemAudit::findOrFail($storedItemAudit->id), $pallet->refresh()),
            'result' => [
                'status'  => $status,
                'message' => $message,
            ],
        ]);
    }
}
