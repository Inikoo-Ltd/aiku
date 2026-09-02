<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sept 2026 10:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentCleanHandoverScore
{
    use AsObject;

    public const int HANDOVER_TOLERANCE_DAYS = 7;

    /**
     * @return array{quarters: array<int, array<string, mixed>>, hygiene: array<string, mixed>}
     */
    public function handle(Agent $agent): array
    {
        $from = Carbon::now()->subQuarters(3)->startOfQuarter();

        $purchaseOrders = AgentSupplierPurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'agent_supplier_purchase_orders.supplier_id')
            ->where('suppliers.agent_id', $agent->id)
            ->whereNotNull('agent_supplier_purchase_orders.approved_ready_at')
            ->where('agent_supplier_purchase_orders.approved_ready_at', '>=', $from)
            ->whereNull('agent_supplier_purchase_orders.cancelled_at')
            ->select('agent_supplier_purchase_orders.*')
            ->get();

        $quarters = [];
        foreach ($purchaseOrders as $purchaseOrder) {
            // ponytail: calendar quarters, not contract-anniversary quarters; align if a contract needs it
            $quarterKey = $purchaseOrder->approved_ready_at->year.'-Q'.$purchaseOrder->approved_ready_at->quarter;
            $value      = (float) ($purchaseOrder->cost_items ?? $purchaseOrder->cost_total);

            $quarter = $quarters[$quarterKey] ?? [
                'quarter'         => $quarterKey,
                'scheduled_value' => 0.0,
                'clean_value'     => 0.0,
                'excluded_value'  => 0.0,
                'number_pos'      => 0,
                'number_clean'    => 0,
            ];

            if ($purchaseOrder->chs_excluded) {
                $quarter['excluded_value'] += $value;
                $quarters[$quarterKey] = $quarter;
                continue;
            }

            $quarter['scheduled_value'] += $value;
            $quarter['number_pos']++;

            if ($this->isCleanHandover($purchaseOrder)) {
                $quarter['clean_value'] += $value;
                $quarter['number_clean']++;
            }

            $quarters[$quarterKey] = $quarter;
        }

        krsort($quarters);

        $quarters = array_map(function (array $quarter) {
            $chs = $quarter['scheduled_value'] > 0
                ? round($quarter['clean_value'] / $quarter['scheduled_value'] * 100, 2)
                : null;

            $quarter['chs']             = $chs;
            $quarter['commission_rate'] = match (true) {
                $chs === null => null,
                $chs >= 80    => 3.0,
                $chs >= 70    => 2.5,
                default       => 2.0,
            };

            return $quarter;
        }, array_values($quarters));

        return [
            'quarters' => $quarters,
            'hygiene'  => $this->hygiene($purchaseOrders),
        ];
    }

    public function isCleanHandover(AgentSupplierPurchaseOrder $purchaseOrder): bool
    {
        if (!$purchaseOrder->handed_over_at || !$purchaseOrder->approved_ready_at) {
            return false;
        }

        // ponytail: quantity test proxied by not_received_at flag; upgrade to per-line ordered-vs-received when receipts are reconciled to ASPOs
        return $purchaseOrder->handed_over_at->lte($purchaseOrder->approved_ready_at->copy()->addDays(self::HANDOVER_TOLERANCE_DAYS))
            && $purchaseOrder->qc_passed_at !== null
            && $purchaseOrder->compliance_complete_at !== null
            && $purchaseOrder->not_received_at === null;
    }

    private function hygiene($purchaseOrders): array
    {
        $gaps          = [];
        $totalValue    = 0.0;
        $excludedValue = 0.0;
        $incomplete    = 0;

        foreach ($purchaseOrders as $purchaseOrder) {
            $value       = (float) ($purchaseOrder->cost_items ?? $purchaseOrder->cost_total);
            $totalValue += $value;

            if ($purchaseOrder->chs_excluded) {
                $excludedValue += $value;
            }

            if ($purchaseOrder->proposed_ready_at) {
                $gaps[] = $purchaseOrder->proposed_ready_at->diffInDays($purchaseOrder->approved_ready_at, false);
            }

            if ($purchaseOrder->handed_over_at
                && (!$purchaseOrder->qc_passed_at || !$purchaseOrder->compliance_complete_at)) {
                $incomplete++;
            }
        }

        return [
            'avg_ready_date_padding_days' => $gaps ? round(array_sum($gaps) / count($gaps), 1) : null,
            'exclusion_rate'              => $totalValue > 0 ? round($excludedValue / $totalValue * 100, 2) : null,
            'handed_over_missing_checks'  => $incomplete,
        ];
    }
}
