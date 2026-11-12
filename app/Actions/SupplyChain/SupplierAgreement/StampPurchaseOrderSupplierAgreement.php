<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The agreement applies to orders accepted after the supplier accepted it (clause 11), so when an order to a supplier
 * is confirmed it keeps the acceptance in force at that moment. Called from the purchase order model, so every way an
 * order gets its confirmation date (the agent or buyer confirming, a stage marked, a deposit paid) stamps it, and a
 * confirmation date moved back (a deposit recorded late) takes the acceptance in force on that earlier date. Orders
 * through an agent or to one of our own organisations name no single supplier and are not stamped.
 */
class StampPurchaseOrderSupplierAgreement
{
    use AsObject;

    public function handle(PurchaseOrder $purchaseOrder): void
    {
        if (!$purchaseOrder->isDirty('confirmed_at')) {
            return;
        }

        if ($purchaseOrder->confirmed_at === null) {
            $purchaseOrder->supplier_agreement_acceptance_id = null;

            return;
        }

        if (!$purchaseOrder->supplier_id) {
            return;
        }

        $purchaseOrder->supplier_agreement_acceptance_id = SupplierAgreementAcceptance::where('supplier_id', $purchaseOrder->supplier_id)
            ->where('accepted_at', '<=', $purchaseOrder->confirmed_at)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->value('id');
    }
}
