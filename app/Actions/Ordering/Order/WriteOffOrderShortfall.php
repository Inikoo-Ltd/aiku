<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Adjustment\StoreAdjustment;
use App\Actions\Ordering\Transaction\StoreTransactionFromAdjustment;
use App\Actions\OrgAction;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Settles a last-cents payment discrepancy in either direction: an adjustment transaction
 * (negative when underpaid, positive when overpaid) moves the net so that net + adjustment
 * + taxes equals what was actually paid. The total itself moves, so this can only run
 * before the order is invoiced.
 */
class WriteOffOrderShortfall extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
    {
        $discrepancy = round($order->total_amount - $order->payment_amount, 2);

        if ($discrepancy == 0) {
            return [
                'success' => false,
                'reason'  => __('Order has no payment discrepancy'),
            ];
        }

        if (abs($discrepancy) > paymentSettlementTolerance($order->shop)) {
            return [
                'success' => false,
                'reason'  => __('Outstanding amount is above the write off limit'),
            ];
        }

        if ($order->invoices()->where('in_process', false)->exists()) {
            return [
                'success' => false,
                'reason'  => __('Order has already been invoiced'),
            ];
        }

        $taxRate   = (float)($order->taxCategory?->rate ?? 0);
        $netAmount = -round($discrepancy / (1 + $taxRate), 2);

        DB::transaction(function () use ($order, $netAmount, $taxRate) {
            $adjustment = StoreAdjustment::make()->action($order->shop, [
                'type'       => AdjustmentTypeEnum::ERROR_NET,
                'net_amount' => $netAmount,
                'tax_amount' => round($netAmount * $taxRate, 2),
            ]);

            $transaction = StoreTransactionFromAdjustment::make()->action($order, $adjustment, [
                'quantity_ordered' => 1,
                'net_amount'       => $netAmount,
                'gross_amount'     => $netAmount,
                'tax_category_id'  => $order->tax_category_id,
            ]);

            /** Tax is rounded across the whole breakdown, so the recalculated total can land
             * a cent off the paid amount; nudge the adjustment net until they match */
            $attemptsLeft = 3;
            while (($residual = round($order->refresh()->total_amount - $order->payment_amount, 2)) != 0 && $attemptsLeft--) {
                $netAmount = round($netAmount - $residual / (1 + $taxRate), 2);
                $transaction->update([
                    'net_amount'   => $netAmount,
                    'gross_amount' => $netAmount,
                ]);
                $adjustment->update([
                    'net_amount' => $netAmount,
                    'tax_amount' => round($netAmount * $taxRate, 2),
                ]);
                CalculateOrderTotalAmounts::run($order);
            }

            UpdateOrderPaymentsStatus::run($order->refresh());
        });

        return [
            'success' => true,
            'reason'  => __('Discrepancy written off'),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): array
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);
        $result = $this->handle($order);

        $request->session()->flash('notification', [
            'status'      => $result['success'] ? 'success' : 'error',
            'title'       => $result['reason'],
            'description' => ''
        ]);
    }
}
