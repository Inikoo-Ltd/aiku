<?php

namespace App\Actions\Traits;

use App\Models\Dispatching\DeliveryNoteItem;


trait WithDeliveryNoteAudit
{
    public function auditDeliveryNoteItem(DeliveryNoteItem $deliveryNoteItem, string $event, array $oldValues, array $newValues): void
    {
        if (empty($oldValues)) return;

        $itemName = $deliveryNoteItem->orgStock?->name ?? $deliveryNoteItem->id;

        $deliveryNoteItem->deliveryNote->audits()->create([
            'event'      => $event,
            'old_values' => array_merge(['item' => $itemName], $oldValues),
            'new_values' => array_merge(['item' => $itemName], $newValues),
            'url'        => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_type'  => get_class(request()->user()),
            'user_id'    => request()->user()?->id,
        ]);
    }
}