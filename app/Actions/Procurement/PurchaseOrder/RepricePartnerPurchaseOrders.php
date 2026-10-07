<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lines added to a sister-company purchase order before it was priced at the seller's landed cost
 * still carry the list price estimate; this brings the orders not yet sent to the seller onto it.
 * Orders to the manufacturing hub keep their list price.
 */
class RepricePartnerPurchaseOrders
{
    use AsAction;

    public string $commandSignature = 'procurement:reprice_partner_purchase_orders {purchase_order? : Slug of one purchase order, all open partner orders when left out}';

    /**
     * @return array{orders: int, lines: int}
     */
    public function handle(?PurchaseOrder $onlyPurchaseOrder = null): array
    {
        $purchaseOrders = PurchaseOrder::where('parent_type', class_basename(OrgPartner::class))
            ->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED])
            ->whereNull('source_id')
            ->when($onlyPurchaseOrder, fn ($query) => $query->where('id', $onlyPurchaseOrder->id))
            ->get();

        $store  = StorePurchaseOrderTransaction::make();
        $orders = 0;
        $lines  = 0;

        foreach ($purchaseOrders as $purchaseOrder) {
            /** @var OrgPartner $orgPartner */
            $orgPartner = $purchaseOrder->parent;
            if (!GetPartnerLandedCost::appliesTo($orgPartner)) {
                continue;
            }

            $repriced = DB::transaction(function () use ($purchaseOrder, $orgPartner, $store) {
                $repriced = 0;
                foreach ($purchaseOrder->purchaseOrderTransactions()->with('orgStock')->get() as $transaction) {
                    $unitCost = $transaction->orgStock ? $store->partnerLandedUnitCost($orgPartner, $transaction->orgStock) : null;
                    if ($unitCost === null || abs($unitCost - (float) $transaction->unit_cost) < 0.000001) {
                        continue;
                    }

                    $transaction->update([
                        'unit_cost'  => round($unitCost, 6),
                        'net_amount' => round($unitCost * (float) $transaction->quantity_ordered, 2),
                    ]);
                    $repriced++;
                }

                if ($repriced) {
                    CalculatePurchaseOrderTotalAmounts::make()->handle($purchaseOrder);
                }

                return $repriced;
            });

            if ($repriced) {
                $orders++;
                $lines += $repriced;
            }
        }

        return ['orders' => $orders, 'lines' => $lines];
    }

    public function asCommand($command): int
    {
        Nightwatch::dontSample();

        $purchaseOrder = $command->argument('purchase_order')
            ? PurchaseOrder::where('slug', $command->argument('purchase_order'))->firstOrFail()
            : null;

        $result = $this->handle($purchaseOrder);

        $command->info("Repriced {$result['lines']} lines on {$result['orders']} purchase orders");

        return 0;
    }
}
