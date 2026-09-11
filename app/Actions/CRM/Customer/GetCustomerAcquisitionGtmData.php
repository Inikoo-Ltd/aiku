<?php

namespace App\Actions\CRM\Customer;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerAcquisitionGtmData
{
    use AsObject;

    public const int NEW_CUSTOMER_INACTIVITY_DAYS = 540;

    /**
     * @return array{new_customer: bool|null, customer_lifetime_value: float|null}
     */
    public function handle(Order $order): array
    {
        if (!$order->customer_id) {
            return [
                'new_customer'            => null,
                'customer_lifetime_value' => null,
            ];
        }

        $inactivityCutoff = now()->subDays(self::NEW_CUSTOMER_INACTIVITY_DAYS);

        return [
            'new_customer'            => !$this->hasPurchasedBeforeOrderSince($order, $inactivityCutoff),
            'customer_lifetime_value' => $this->getCustomerLifetimeValue($order->customer_id),
        ];
    }

    private function hasPurchasedBeforeOrderSince(Order $order, Carbon $since): bool
    {
        $hasOtherPlacedOrder = DB::table('orders')
            ->where('customer_id', $order->customer_id)
            ->where('id', '!=', $order->id)
            ->whereNull('deleted_at')
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->where('date', '>=', $since)
            ->exists();

        if ($hasOtherPlacedOrder) {
            return true;
        }

        return DB::table('invoices')
            ->where('customer_id', $order->customer_id)
            ->whereNull('deleted_at')
            ->where('type', InvoiceTypeEnum::INVOICE->value)
            ->where('in_process', false)
            ->where(function ($query) use ($order) {
                $query->whereNull('order_id')->orWhere('order_id', '!=', $order->id);
            })
            ->where('date', '>=', $since)
            ->exists();
    }

    private function getCustomerLifetimeValue(int $customerId): float
    {
        $invoicedTotalNetOfRefunds = DB::table('invoices')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->where('in_process', false)
            ->sum('total_amount');

        return round((float)$invoicedTotalNetOfRefunds, 2);
    }
}
