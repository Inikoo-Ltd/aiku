<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderCancellationReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A trade shop that opts in (INI-163) chases orders the customer chose to pay by bank transfer:
 * a reminder every few days, a final reminder the day before the cancel day, and the cancellation.
 *
 * Days count from when the order was placed and each stage fires on its exact day, so orders that
 * were already past the final reminder when the shop opted in are never cancelled: they stay CS's to
 * chase. An order is only cancelled once its final reminder was actually sent, before today.
 */
class RemindUnpaidBankTransferOrders
{
    use AsAction;

    public string $commandSignature = 'orders:remind_unpaid_bank_transfer_orders';
    public string $commandDescription = 'Remind unpaid bank transfer orders, and cancel the ones given a final reminder, in shops that opted in';

    /**
     * @return array{reminded: array<int, int>, warned: array<int, int>, cancelled: array<int, int>} order ids
     */
    public function handle(?Command $command = null): array
    {
        $result = ['reminded' => [], 'warned' => [], 'cancelled' => []];
        $today  = now()->toDateString();

        foreach (Shop::where('type', ShopTypeEnum::B2B)->get() as $shop) {
            if (!$shop->cancelsUnpaidBankOrders()) {
                continue;
            }

            $reminderEveryDays = (int) $shop->unpaidBankOrderSetting('reminder_every_days');
            $cancelAfterDays   = (int) $shop->unpaidBankOrderSetting('cancel_after_days');

            foreach ($this->unpaidBankTransferOrders($shop)->get() as $order) {
                /** Calendar days, like the date the final reminder gives: an order placed in the afternoon is still on day 1 tomorrow morning */
                $day           = (int) $order->submitted_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
                $finalReminder = data_get($order->data, 'final_payment_reminder_at');

                if ($day >= $cancelAfterDays && $finalReminder && $finalReminder < now()->startOfDay()->toIso8601String()) {
                    if ($this->cancel($order)) {
                        $result['cancelled'][] = $order->id;
                    }

                    continue;
                }

                /** Marked when queued, so a second run on the same day does not send it again */
                if (data_get($order->data, 'last_payment_reminder_sent_on') === $today) {
                    continue;
                }

                $reminder = match (true) {
                    $day === $cancelAfterDays - 1 && !$finalReminder => SendOrderPaymentReminderEmail::FINAL_REMINDER,
                    $day > 0 && $day < $cancelAfterDays - 1 && $day % $reminderEveryDays === 0 => SendOrderPaymentReminderEmail::REMINDER,
                    default => null,
                };

                if (!$reminder) {
                    continue;
                }

                $order->update(['data' => array_merge((array) $order->data, ['last_payment_reminder_sent_on' => $today])]);
                SendOrderPaymentReminderEmail::dispatch($order->id, $reminder);
                $result[$reminder === SendOrderPaymentReminderEmail::FINAL_REMINDER ? 'warned' : 'reminded'][] = $order->id;
            }
        }

        $command?->info(count($result['reminded']).' reminded, '.count($result['warned']).' warned, '.count($result['cancelled']).' cancelled');

        return $result;
    }

    protected function cancel(Order $order): bool
    {
        try {
            CancelOrder::make()->action($order, [
                'cancellation_reason' => OrderCancellationReasonEnum::UNPAID_PAST_DEADLINE,
                'cancellation_notes'  => 'Bank transfer not received by the date given in the final payment reminder',
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Only orders the customer chose to pay by bank transfer: cash on delivery is paid at the door,
     * PastPay is paid by PastPay, and customers on account (credit customer or a credit limit) pay
     * on their own terms.
     */
    protected function unpaidBankTransferOrders(Shop $shop): Builder
    {
        return Order::query()
            ->where('orders.shop_id', $shop->id)
            ->where('orders.state', OrderStateEnum::SUBMITTED)
            ->payNotSettled()
            ->where('orders.to_be_paid_by', OrderToBePaidByEnum::BANK)
            ->where(fn (Builder $query) => $query->where('orders.is_pastpay', false)->orWhereNull('orders.is_pastpay'))
            ->whereNotNull('orders.submitted_at')
            ->whereHas('customer', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('is_credit_customer', false)->orWhereNull('is_credit_customer'))
                ->where(fn (Builder $query) => $query->where('credit_limit', '<=', 0)->orWhereNull('credit_limit')));
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
