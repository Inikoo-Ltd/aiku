<?php

namespace App\Actions\CRM\Customer;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class WriteOffCustomerBalancesInClosedShop
{
    use AsAction;

    public string $commandSignature = 'crm:write_off_balances_in_closed_shops {organisation?} {--commit : Apply the write-offs, otherwise dry run}';

    public function asCommand(Command $command): int
    {
        $commit = (bool)$command->option('commit');

        $shops = Shop::where('state', ShopStateEnum::CLOSED)
            ->when($command->argument('organisation'), function ($query) use ($command) {
                $query->whereHas('organisation', fn ($q) => $q->where('slug', $command->argument('organisation')));
            })
            ->get();

        $customers = 0;
        $total     = 0;
        foreach ($shops as $shop) {
            foreach ($shop->customers()->where('balance', '!=', 0)->get() as $customer) {
                $command->line(($commit ? 'WRITE OFF ' : 'WOULD WRITE OFF ')."{$customer->balance} for {$shop->slug}/{$customer->slug} ({$customer->name})");
                if ($commit) {
                    StoreCreditTransaction::make()->action($customer, [
                        'amount' => -$customer->balance,
                        'type'   => CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER,
                        'reason' => CreditTransactionReasonEnum::OTHER,
                        'notes'  => 'Balance written off: shop closed',
                        'date'   => $shop->closed_at ?? now(),
                    ]);
                }
                $customers++;
                $total += $customer->balance;
            }
        }

        $command->info(($commit ? 'Written off' : 'Would write off').": $total across $customers customers in closed shops");

        return 0;
    }
}
