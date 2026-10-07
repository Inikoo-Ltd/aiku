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
use Illuminate\Validation\Rule;
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
     * cannot build two orders. With a budget (in our currency, for the lines added this time) a line that would
     * take the order over it is skipped and the next, cheaper ones still get their chance.
     *
     * @throws ValidationException
     */
    /**
     * @param  array<int, string>  $buckets
     */
    public function handle(OrgPartner $orgPartner, ?float $budget = null, array $buckets = GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS, bool $worstOnly = true): PurchaseOrder
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['rescue' => $message]);

        if ($orgPartner->partner->is_manufacturing_hub) {
            $fail(__('Buy from :partner with the shopping list', ['partner' => $orgPartner->partner->name]));
        }

        return DB::transaction(function () use ($orgPartner, $fail, $budget, $buckets, $worstOnly) {
            OrgPartner::whereKey($orgPartner->id)->lockForUpdate()->first();

            $lines = GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner, $buckets, $worstOnly);
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
            $skippedForBudget          = 0;
            $spent                     = 0.0;

            foreach ($lines as $line) {
                if ($alreadyOrdered->has($line['org_stock_id']) || !$orgStock = $orgStocks->get($line['org_stock_id'])) {
                    continue;
                }

                if ($budget !== null) {
                    if ($line['cost'] <= 0) {
                        continue;
                    }
                    if ($spent + $line['cost'] > $budget) {
                        $skippedForBudget++;
                        continue;
                    }
                }

                try {
                    $transaction = $storeTransaction->addPartnerOrgStock($purchaseOrder, $orgStock, ['quantity_ordered' => $line['quantity']]);
                    $spent       += (float) $transaction->org_net_amount;
                    $added++;
                } catch (ValidationException) {
                    continue;
                }
            }

            if ($added === 0) {
                $fail($skippedForBudget
                    ? __('Nothing fits in a budget of :amount', ['amount' => $orgPartner->organisation->currency->code.' '.number_format($budget, 2)])
                    : __('Everything :partner can rescue is already on :reference', ['partner' => $orgPartner->partner->name, 'reference' => $purchaseOrder->reference]));
            }

            $purchaseOrder->update(['is_partner_rescue' => true]);
            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::run($purchaseOrder);

            return $purchaseOrder;
        });
    }

    public function rules(): array
    {
        return [
            'budget'     => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'buckets'    => ['sometimes', 'array', 'min:1'],
            'buckets.*'  => ['string', Rule::in(['out', 'w1', 'w2'])],
            'worst_only' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(OrgPartner $orgPartner, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($orgPartner->organisation, $request);

        $budget = $this->validatedData['budget'] ?? null;

        return $this->handle(
            $orgPartner,
            $budget === null ? null : (float) $budget,
            $this->validatedData['buckets'] ?? GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS,
            (bool) ($this->validatedData['worst_only'] ?? true)
        );
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
