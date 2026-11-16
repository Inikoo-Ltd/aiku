<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 15:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;

/**
 * Orders a bank line could pay, for the Pay order dialog of the bank review: orders of this organisation in the line's
 * currency, submitted or later and not cancelled, that still owe money, found by order number or customer name.
 * Only suggestions: paying still goes through the same checks.
 */
class GetBankTransactionOrderCandidates extends OrgAction
{
    use WithAccountingPagesAuthorisation;

    /**
     * @return list<array{id: int, reference: string, customer: string|null, shop: string|null, owed: float, total: float, currency_code: string, date: string|null}>
     */
    public function handle(BankTransaction $bankTransaction, string $search): array
    {
        $search = trim($search);
        if (mb_strlen($search) < 2) {
            return [];
        }

        return Order::query()
            ->where('orders.organisation_id', $bankTransaction->organisation_id)
            ->where('orders.currency_id', $bankTransaction->currency_id)
            ->whereNotIn('orders.state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
            ->whereRaw('orders.total_amount - orders.payment_amount > 0.01')
            ->join('customers', 'customers.id', 'orders.customer_id')
            ->join('shops', 'shops.id', 'orders.shop_id')
            ->join('currencies', 'currencies.id', 'orders.currency_id')
            ->where(function (Builder $query) use ($search) {
                $query->whereStartWith('orders.reference', $search)
                    ->orWhere(fn (Builder $query) => $query->whereAnyWordStartWith('customers.name', $search));
            })
            ->orderByDesc('orders.date')
            ->limit(15)
            ->get([
                'orders.id',
                'orders.reference',
                'orders.date',
                'orders.total_amount',
                'orders.payment_amount',
                'customers.name as customer_name',
                'shops.code as shop_code',
                'currencies.code as currency_code',
            ])
            ->map(fn (Order $order) => [
                'id'            => $order->id,
                'reference'     => $order->reference,
                'customer'      => $order->customer_name,
                'shop'          => $order->shop_code,
                'owed'          => round((float)$order->total_amount - (float)$order->payment_amount, 2),
                'total'         => (float)$order->total_amount,
                'currency_code' => $order->currency_code,
                'date'          => $order->date?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, (string)$request->query('search', ''));
    }
}
