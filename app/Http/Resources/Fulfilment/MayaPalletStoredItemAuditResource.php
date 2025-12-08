<?php

/*
 * author Arya Permana - Kirin
 * created on 19-03-2025-14h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Actions\Fulfilment\StoredItemAuditDelta\UI\IndexStoredItemAuditDeltas;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $fulfilment_customer_name
 * @property string $fulfilment_customer_id
 * @property string $fulfilment_customer_slug
 * @property string $public_notes
 * @property string $internal_notes
 * @property StoredItemAuditStateEnum $state
 */
class MayaPalletStoredItemAuditResource extends JsonResource
{
    public function toArray($request): array
    {
        $storedItemAudit = StoredItemAudit::find($this->id);
        $pallet = $storedItemAudit->scope;
        $editDeltas = [
            'stored_items' => $pallet->getEditStoredItemDeltasQuery($pallet->id, $this->id)
                ->where('pallet_stored_items.pallet_id', $pallet->id)
                ->get()->map(fn ($item) => [
                'stored_item_audit_id' => $this->id,
                'pallet_id' => $item->pallet_id,
                'stored_item_id' => $item->stored_item_id,
                'reference' => $item->stored_item_reference,
                'quantity' => (int) $item->quantity,
                'audited_quantity' => (int) $item->audited_quantity,
                'audit_notes' => $item->audit_notes,
                'stored_item_audit_delta_id' => $item->stored_item_audit_delta_id,
                'audit_type' => $item->audit_type,
                'type' => 'current_item',
            ]),

            'new_stored_items' => $pallet->getEditNewStoredItemDeltasQuery($pallet->id)
                ->where('stored_item_audit_deltas.pallet_id', $pallet->id)
                ->where('stored_item_audit_deltas.stored_item_audit_id', $this->id)
                ->get()->map(fn ($item) => [
                    'stored_item_audit_id' => $this->id,
                    'stored_item_id' => $item->stored_item_id,
                    'reference' => $item->stored_item_reference,
                    'quantity' => 0,
                    'audited_quantity' => (int) $item->audited_quantity,
                    'stored_item_audit_delta_id' => $item->audit_id,
                    'audit_type' => $item->audit_type,
                    'audit_notes' => $item->audit_notes,
                    'type' => 'new_item',
                ]),
        ];

        $mergedItems = collect($editDeltas['stored_items'])
            ->map(fn ($item) => array_merge($item, ['is_new' => false]))
            ->merge(
                collect($editDeltas['new_stored_items'])
                    ->map(fn ($item) => array_merge($item, ['is_new' => true]))
            )
            ->values();

        $deltas = StoredItemAuditDeltasResource::collection(IndexStoredItemAuditDeltas::run($storedItemAudit, 'stored_item_deltas'));

        return [
            'id' => $this->id,
            'scope_id' => $this->scope ? $this->scope->id : null,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'fulfilment_customer_name' => $this->fulfilment_customer_name,
            'fulfilment_customer_slug' => $this->fulfilment_customer_slug,
            'fulfilment_customer_id' => $this->fulfilment_customer_id,
            'public_notes' => $this->public_notes,
            'internal_notes' => $this->internal_notes,
            'state' => $this->state,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'editDeltas' => $mergedItems,
            'deltas' => $deltas,
            'number_audited_pallets' => $this->number_audited_pallets,
            'number_audited_stored_items' => $this->number_audited_stored_items,
            'number_audited_stored_items_with_additions' => $this->number_audited_stored_items_with_additions,
            'number_audited_stored_items_with_with_subtractions' => $this->number_audited_stored_items_with_with_subtractions,
            'number_audited_stored_items_with_with_stock_checked' => $this->number_audited_stored_items_with_with_stock_checked,
            'number_associated_stored_items' => $this->number_associated_stored_items,
            'number_created_stored_items' => $this->number_created_stored_items,
        ];
    }
}
