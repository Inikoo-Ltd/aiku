<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Relabels historical paid-then-refunded orders stuck on pay_detailed_status unpaid/unknown:
 * a card payment followed by a refund payment nets payment_amount to zero, or a refund invoice
 * nets the total to zero, and the order read as unpaid forever. Only re-runs
 * UpdateOrderPaymentsStatus on candidates, so the new REFUNDED rule decides. Dry run unless
 * --commit is given.
 */
class BackfillRefundedPayDetailedStatus
{
    use AsAction;

    public string $commandSignature = 'orders:backfill_refunded_pay_detailed_status {--commit} {--shop=} {--limit=0}';

    public function asCommand(Command $command): int
    {
        $commit = (bool) $command->option('commit');
        $limit  = (int) $command->option('limit');

        $query = Order::query()
            ->whereIn('pay_detailed_status', [OrderPayDetailedStatusEnum::UNPAID, OrderPayDetailedStatusEnum::UNKNOWN])
            ->where(function ($q) {
                $q->whereHas('payments', function ($q) {
                    $q->where('payments.status', PaymentStatusEnum::SUCCESS)
                        ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
                        ->where('payments.amount', '<', 0);
                })->orWhereIn('orders.id', Invoice::where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->whereNotNull('order_id')->select('order_id'));
            })
            ->whereHas('payments', function ($q) {
                $q->where('payments.status', PaymentStatusEnum::SUCCESS)
                    ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
                    ->where('payments.amount', '>', 0);
            })
            ->orderBy('orders.id');

        if ($command->option('shop')) {
            $query->whereIn('orders.shop_id', Shop::where('slug', $command->option('shop'))->select('id'));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $counts = ['relabelled' => 0, 'unchanged' => 0];

        foreach ($query->cursor() as $order) {
            if ($this->wouldBeRefunded($order)) {
                $counts['relabelled']++;
                if ($commit) {
                    UpdateOrderPaymentsStatus::run($order);
                } else {
                    $command->line("Order {$order->id} ({$order->reference}): {$order->pay_detailed_status->value} -> refunded");
                }
            } else {
                $counts['unchanged']++;
            }
        }

        $command->info(($commit ? 'Relabelled' : 'Would relabel')." {$counts['relabelled']} orders, {$counts['unchanged']} candidates left unchanged");

        return 0;
    }

    private function wouldBeRefunded(Order $order): bool
    {
        $paidAmount     = 0;
        $refundedAmount = 0;
        foreach ($order->payments()->where('payments.status', PaymentStatusEnum::SUCCESS)->whereNot('payments.state', PaymentStateEnum::CANCELLED)->get() as $payment) {
            if ($payment->amount >= 0) {
                $paidAmount += $payment->amount;
            } else {
                $refundedAmount -= $payment->amount;
            }
        }
        $paidAmount     = round($paidAmount, 2);
        $refundedAmount = round($refundedAmount, 2);

        $totalAmount = $order->total_amount;
        foreach (Invoice::where('order_id', $order->id)->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->get() as $refund) {
            $totalAmount += $refund->total_amount;
        }
        $totalAmount = round($totalAmount, 2);

        return $paidAmount > 0
            && (
                ($refundedAmount > 0 && $paidAmount >= $totalAmount && round($paidAmount - $refundedAmount, 2) <= 0)
                || ($totalAmount == 0 && round($paidAmount - $refundedAmount, 2) >= 0)
            );
    }
}
