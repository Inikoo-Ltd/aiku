<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Helpers\Audit;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use OwenIt\Auditing\Events\Audited;

class BroadcastPurchaseOrderLastEdited implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(public int $purchaseOrderId, public array $lastEdit)
    {
    }

    public static function fromAudited(Audited $event): void
    {
        $audit = $event->audit;
        if (!$audit instanceof Audit || $audit->user_type !== 'User' || !$audit->user_id) {
            return;
        }

        $purchaseOrderId = match (true) {
            $event->model instanceof PurchaseOrder => $event->model->id,
            $event->model instanceof PurchaseOrderTransaction => $event->model->purchase_order_id,
            default => null,
        };
        if (!$purchaseOrderId) {
            return;
        }

        static::dispatch($purchaseOrderId, static::payload($audit));
    }

    public static function lastEdit(PurchaseOrder $purchaseOrder): ?array
    {
        $audit = Audit::query()
            ->where('user_type', 'User')
            ->whereNotNull('user_id')
            ->where(function ($query) use ($purchaseOrder) {
                $query->where(fn ($query) => $query->where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id))
                    ->orWhere(fn ($query) => $query->where('auditable_type', 'PurchaseOrderTransaction')
                        ->whereIn('auditable_id', $purchaseOrder->purchaseOrderTransactions()->select('id')));
            })
            ->orderByDesc('id')
            ->first();

        return $audit ? static::payload($audit) : null;
    }

    private static function payload(Audit $audit): array
    {
        $user = User::find($audit->user_id);

        return [
            'user' => $user?->contact_name ?: $user?->username,
            'at'   => $audit->created_at?->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.purchase_order.'.$this->purchaseOrderId)];
    }

    public function broadcastAs(): string
    {
        return 'last-edited';
    }

    public function broadcastWith(): array
    {
        return $this->lastEdit;
    }
}
