<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 2 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreRescuePurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * A purchase order to a sister company with every SKO it can rescue that would lose sales, and
     * every A/B bestseller, biggest lost sales first. Lines go onto the order already being prepared
     * for that partner, if there is one; lines already on it are left as they are. Nothing is created
     * or flagged when there is nothing new to add, and the partner row is locked so a double click
     * cannot build two orders.
     *
     * @throws ValidationException
     */
    public function handle(OrgPartner $orgPartner): PurchaseOrder
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['rescue' => $message]);

        if ($orgPartner->partner->is_manufacturing_hub) {
            $fail(__('Buy from :partner with the shopping list', ['partner' => $orgPartner->partner->name]));
        }

        return DB::transaction(function () use ($orgPartner, $fail) {
            OrgPartner::whereKey($orgPartner->id)->lockForUpdate()->first();

            $lines = GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner);
            if (!$lines) {
                $fail(__(':partner has nothing to rescue right now', ['partner' => $orgPartner->partner->name]));
            }

            $purchaseOrder = $orgPartner->purchaseOrders()
                ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
                ->latest()
                ->first() ?? StorePurchaseOrder::make()->action($orgPartner, []);

            $alreadyOrdered = $purchaseOrder->purchaseOrderTransactions()->pluck('org_stock_id')->flip();
            $orgStocks      = OrgStock::whereIn('id', array_column($lines, 'org_stock_id'))->get()->keyBy('id');

            $storeTransaction          = StorePurchaseOrderTransaction::make();
            $storeTransaction->batched = true;
            $added                     = 0;

            foreach ($lines as $line) {
                if ($alreadyOrdered->has($line['org_stock_id']) || !$orgStock = $orgStocks->get($line['org_stock_id'])) {
                    continue;
                }

                try {
                    $storeTransaction->addPartnerOrgStock($purchaseOrder, $orgStock, ['quantity_ordered' => $line['quantity']]);
                    $added++;
                } catch (ValidationException) {
                    continue;
                }
            }

            if ($added === 0) {
                $fail(__('Everything :partner can rescue is already on :reference', ['partner' => $orgPartner->partner->name, 'reference' => $purchaseOrder->reference]));
            }

            $purchaseOrder->update(['is_partner_rescue' => true]);
            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::run($purchaseOrder);

            return $purchaseOrder;
        });
    }

    public function asController(OrgPartner $orgPartner, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($orgPartner->organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return Redirect::route('grp.org.procurement.org_partners.show.purchase-orders.show', [
            $purchaseOrder->organisation->slug,
            $purchaseOrder->parent_id,
            $purchaseOrder->slug,
        ]);
    }
}
