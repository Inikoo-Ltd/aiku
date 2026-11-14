<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Faire payouts booked before marketplace matching existed sit unmatched or in review: decide them again, linking each
 * payout that is exactly its order's total less commission. Then every unpaid Faire order is relabelled to be paid,
 * or paid, by the marketplace. No payment is recorded and no amount changes. Dry run unless --commit.
 */
class BackfillFairePayouts
{
    use AsAction;

    public string $commandSignature = 'bank-transactions:backfill_faire_payouts {--commit}';

    /**
     * @return array<string, int>
     */
    public function handle(bool $commit): array
    {
        $matcher = MatchBankTransactions::make();
        $counts  = [];

        BankTransaction::where('direction', BankTransactionDirectionEnum::IN)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereIn('provider_type', MatchBankTransactions::CUSTOMER_TRANSFER_TYPES)
            ->whereNull('payment_id')
            ->where(fn ($query) => $query->whereNull('match_state')->orWhereIn('match_state', [BankTransactionMatchStateEnum::UNMATCHED, BankTransactionMatchStateEnum::REVIEW]))
            ->where('counterparty_name', 'ilike', 'faire%')
            ->chunkById(500, function ($bankTransactions) use ($matcher, $commit, &$counts) {
                foreach ($bankTransactions as $bankTransaction) {
                    if (!$matcher->marketplaceOrderReference($bankTransaction)) {
                        continue;
                    }

                    [$state, $order, $matchData] = $matcher->decide($bankTransaction);
                    $counts['payouts '.$state->value] = ($counts['payouts '.$state->value] ?? 0) + 1;

                    if ($commit) {
                        $bankTransaction->update([
                            'match_state' => $state,
                            'order_id'    => $order?->id,
                            'match_data'  => $matchData,
                            'matched_at'  => now(),
                        ]);
                        if ($state === BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE) {
                            $matcher->refreshMarketplaceOrders([$order->id]);
                        }
                    }
                }
            });

        $faireShopIds = Shop::where('type', ShopTypeEnum::EXTERNAL)->get()->filter(fn (Shop $shop) => $shop->isFaire())->pluck('id');

        Order::whereIn('shop_id', $faireShopIds)
            ->where('payment_amount', 0)
            ->whereNot('state', OrderStateEnum::CANCELLED)
            ->whereNotIn('pay_detailed_status', [OrderPayDetailedStatusEnum::TO_BE_PAID_BY_MARKETPLACE, OrderPayDetailedStatusEnum::PAID_BY_MARKETPLACE])
            ->chunkById(500, function ($orders) use ($commit, &$counts) {
                foreach ($orders as $order) {
                    $counts['orders relabelled'] = ($counts['orders relabelled'] ?? 0) + 1;
                    if ($commit) {
                        UpdateOrderPaymentsStatus::run($order);
                    }
                }
            });

        return $counts;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $commit = (bool)$command->option('commit');
        foreach ($this->handle($commit) as $outcome => $count) {
            $command->info("$outcome: $count");
        }
        if (!$commit) {
            $command->warn('Dry run: nothing written, run again with --commit');
        }

        return 0;
    }
}
