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
     * cannot build two orders. With a budget (in our currency, for the whole order) a line that would
     * take the order over it is skipped and the next, cheaper ones still get their chance.
     *
     * @throws ValidationException
     */
    /**
     * @param  array<int, string>  $buckets
     */
    public function handle(OrgPartner $orgPartner, ?float $budget = null, array $buckets = ['out', 'w1', 'w2'], bool $worstOnly = true): PurchaseOrder
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
            $spent                     = (float) $purchaseOrder->purchaseOrderTransactions()->sum('org_net_amount');

            foreach ($lines as $line) {
                if ($alreadyOrdered->has($line['org_stock_id']) || !$orgStock = $orgStocks->get($line['org_stock_id'])) {
                    continue;
                }

                if ($budget !== null) {
                    $lineCost = $this->lineCost($orgPartner, $purchaseOrder, $orgStock, $line['quantity']);
                    if ($lineCost === null) {
                        continue;
                    }
                    if ($spent + $lineCost > $budget) {
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
                    ? __('Nothing more fits in the budget, :reference is already at :amount', ['reference' => $purchaseOrder->reference, 'amount' => $orgPartner->organisation->currency->code.' '.number_format($spent, 2)])
                    : __('Everything :partner can rescue is already on :reference', ['partner' => $orgPartner->partner->name, 'reference' => $purchaseOrder->reference]));
            }

            $purchaseOrder->update(['is_partner_rescue' => true]);
            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::run($purchaseOrder);

            return $purchaseOrder;
        });
    }

    /**
     * What the line will cost in our currency, priced exactly as adding it to the order prices it.
     */
    private function lineCost(OrgPartner $orgPartner, PurchaseOrder $purchaseOrder, OrgStock $orgStock, int $quantity): ?float
    {
        $product   = GetPartnerSellingProduct::run($orgPartner, $orgStock->stock_id);
        $unitPrice = $product ? GetPartnerSellingProduct::make()->unitPrice($product) : null;

        return $unitPrice === null ? null : round($unitPrice * GetPartnerBuyingPriceFactor::run($orgPartner) * $quantity, 2) * (float) ($purchaseOrder->org_exchange ?: 1);
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
            $this->validatedData['buckets'] ?? ['out', 'w1', 'w2'],
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
