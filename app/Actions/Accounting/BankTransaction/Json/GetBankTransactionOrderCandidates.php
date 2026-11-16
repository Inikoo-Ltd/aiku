<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 15:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\Json;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
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
 * Searching by the payer looks for the first word of its name without the legal form ("Mahoni" for "Mahoni Health
 * GmbH"). Orders owing this amount within the bank fee come first, then those submitted nearest the day it arrived.
 * Only suggestions: paying still goes through the same checks.
 */
class GetBankTransactionOrderCandidates extends OrgAction
{
    use WithAccountingPagesAuthorisation;

    /**
     * @return list<array{id: int, reference: string, customer: string|null, shop: string|null, owed: float, total: float, currency_code: string, date: string|null, amount_matches: bool}>
     */
    public function handle(BankTransaction $bankTransaction, string $search, bool $byPayer = false): array
    {
        $matcher = MatchBankTransactions::make();
        $search  = $byPayer ? ($matcher->nameWords($bankTransaction->counterparty_name)[0] ?? '') : trim($search);
        if (mb_strlen($search) < 2) {
            return [];
        }
        $received  = round((float)$bankTransaction->amount, 2);
        $tolerance = max(0.01, $matcher->feeTolerance($received, $bankTransaction));
        $bookedAt  = $bankTransaction->booked_at ?? now();

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
            ->limit(60)
            ->get([
                'orders.id',
                'orders.reference',
                'orders.date',
                'orders.submitted_at',
                'orders.total_amount',
                'orders.payment_amount',
                'customers.name as customer_name',
                'shops.code as shop_code',
                'currencies.code as currency_code',
            ])
            ->map(function (Order $order) use ($received, $tolerance, $bookedAt) {
                $owed = round((float)$order->total_amount - (float)$order->payment_amount, 2);

                return [
                    'id'             => $order->id,
                    'reference'      => $order->reference,
                    'customer'       => $order->customer_name,
                    'shop'           => $order->shop_code,
                    'owed'           => $owed,
                    'total'          => (float)$order->total_amount,
                    'currency_code'  => $order->currency_code,
                    'date'           => $order->date?->toDateString(),
                    'amount_matches' => abs($owed - $received) <= $tolerance,
                    'days_away'      => abs($bookedAt->diffInDays($order->submitted_at ?? $order->date ?? $bookedAt)),
                ];
            })
            ->sortBy([['amount_matches', 'desc'], ['days_away', 'asc']])
            ->take(15)
            ->map(fn (array $candidate) => collect($candidate)->except('days_away')->all())
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

        return $this->handle($bankTransaction, (string)$request->query('search', ''), $request->boolean('payer'));
    }
}
