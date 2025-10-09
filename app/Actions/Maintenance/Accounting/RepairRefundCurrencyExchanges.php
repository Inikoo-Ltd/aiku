<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairRefundCurrencyExchanges
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        $orgExchange = GetHistoricCurrencyExchange::run($invoice->shop->currency, $invoice->organisation->currency, $invoice->date);
        $grpExchange = GetHistoricCurrencyExchange::run($invoice->shop->currency, $invoice->group->currency, $invoice->date);


        $invoice->update([
            'org_exchange'   => $orgExchange,
            'grp_exchange'   => $grpExchange,
            'org_net_amount' => $invoice->net_amount * $orgExchange,
            'grp_net_amount' => $invoice->net_amount * $grpExchange,
        ]);


    }

    public string $commandSignature = 'repair:invoice_exchanges';

    public function asCommand(Command $command): void
    {
        $invoice = Invoice::find(1018279);
        $this->handle($invoice);

        $count = Invoice::whereNull('source_id')->whereDate('date', '>', '2025-01-01')
            ->count();

        $command->info("pending: $count");

        Invoice::whereNull('source_id')->whereDate('date', '>', '2025-01-01')
            ->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
