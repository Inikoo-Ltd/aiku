<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 16:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Notifications\SupplierDepositAnsweredNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Emails the supplier what happened to the deposit it asked for from its portal, each contact with a fresh private
 * link to its page, where the order shows the same message. The contact who asked is told; when it lost its access
 * every contact of the supplier is. A paid deposit is told once, when the deposit stage is first reached.
 */
class NotifySupplierOfDepositAnswer
{
    use AsObject;

    public const int LINK_DAYS = 7;

    public function paid(PurchaseOrder $purchaseOrder): int
    {
        $request = Arr::get($purchaseOrder->data ?? [], 'supplier_deposit_request');
        if (!$request || isset($request['paid_notified_at']) || !$purchaseOrder->isDepositPaid()) {
            return 0;
        }

        $data = $purchaseOrder->data;
        $data['supplier_deposit_request']['paid_notified_at'] = now()->toIso8601String();
        $purchaseOrder->update(['data' => $data]);

        return $this->send(
            $purchaseOrder,
            $request,
            __(':reference: deposit paid', ['reference' => $purchaseOrder->reference]),
            __('Deposit paid, please add your production dates to take us to the next stage.')
        );
    }

    /**
     * @param  array{percentage: int|float, amount: float, supplier_portal_contact_id?: int|null}  $request
     */
    public function rejected(PurchaseOrder $purchaseOrder, array $request, string $reason): int
    {
        return $this->send(
            $purchaseOrder,
            $request,
            __(':reference: deposit request rejected', ['reference' => $purchaseOrder->reference]),
            __('Your deposit request has been rejected because :reason. Please resubmit to continue.', ['reason' => rtrim($reason, '. ')])
        );
    }

    /**
     * @param  array{supplier_portal_contact_id?: int|null}  $request
     */
    private function send(PurchaseOrder $purchaseOrder, array $request, string $title, string $body): int
    {
        $purchaseOrder->loadMissing('group');
        $contacts = $this->recipients($purchaseOrder, $request['supplier_portal_contact_id'] ?? null);

        foreach ($contacts as $contact) {
            $link = StoreSupplierShareLink::make()->handle($contact->supplier, [
                'days'                       => self::LINK_DAYS,
                'supplier_portal_contact_id' => $contact->id,
            ]);

            Notification::route('mail', $contact->email)->notify(
                new SupplierDepositAnsweredNotification($title, $body, $link['url'], $purchaseOrder->group->name)
            );
        }

        return $contacts->count();
    }

    /**
     * @return Collection<int, SupplierPortalContact>
     */
    public function recipients(PurchaseOrder $purchaseOrder, ?int $requesterId): Collection
    {
        $requester = $requesterId ? SupplierPortalContact::authorised()->with('supplier')->find($requesterId) : null;
        if ($requester) {
            return collect([$requester]);
        }

        $supplier = $this->supplierOf($purchaseOrder);
        if (!$supplier) {
            return collect();
        }

        return SupplierPortalContact::authorised()->where('supplier_id', $supplier->id)->with('supplier')->get();
    }

    private function supplierOf(PurchaseOrder $purchaseOrder): ?Supplier
    {
        if ($purchaseOrder->supplier_id) {
            return Supplier::find($purchaseOrder->supplier_id);
        }

        return $purchaseOrder->parent instanceof OrgSupplier ? $purchaseOrder->parent->supplier : null;
    }
}
