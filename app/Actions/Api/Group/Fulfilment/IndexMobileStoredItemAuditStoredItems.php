<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileStoredItemAuditStoredItems
{
    use AsController;
    use WithMobileStoredItemAudits;

    public function asController(Warehouse $warehouse, StoredItemAudit $storedItemAudit, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        $pallet = $this->palletOfAudit($warehouse, $storedItemAudit);

        $filters = Validator::make($request->query(), [
            'q' => ['sometimes', 'nullable', 'string', 'max:64'],
        ])->validate();
        $search = trim((string) ($filters['q'] ?? ''));

        $storableStates = collect(StoredItemStateEnum::cases())->filter(fn (StoredItemStateEnum $state) => $state->canBeStored())->map(fn (StoredItemStateEnum $state) => $state->value)->values()->all();

        $storedItems = StoredItem::query()
            ->where('fulfilment_customer_id', $pallet->fulfilment_customer_id)
            ->whereIn('state', $storableStates)
            ->whereNotIn('id', DB::table('pallet_stored_items')->where('pallet_id', $pallet->id)->select('stored_item_id'))
            ->whereNotIn('id', DB::table('stored_item_audit_deltas')->where('stored_item_audit_id', $storedItemAudit->id)->select('stored_item_id'))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->whereStartWith('stored_items.reference', $search)
                    ->orWhereStartWith('stored_items.barcode', $search)
                    ->orWhereWith('stored_items.name', $search);
            }))
            ->orderBy('reference')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $storedItems->map(fn (StoredItem $storedItem) => [
                'id'        => $storedItem->id,
                'reference' => $storedItem->reference,
                'name'      => $storedItem->name,
                'barcode'   => $storedItem->barcode,
            ])->values()->all(),
        ]);
    }
}
