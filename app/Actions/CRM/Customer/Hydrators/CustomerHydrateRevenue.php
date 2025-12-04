<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateInvoices;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateRevenue implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateInvoices;

    public string $commandSignature = 'hydrate:customer-revenue {customer}';

    public function getJobUniqueId(int $customerId): string
    {
        return $customerId;
    }

    public function asCommand(Command $command): void
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();

        $this->handle($customer->id);
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $invoices = $customer->invoices()
            ->where('in_process', false)
            ->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $sales = $invoices->where('type', InvoiceTypeEnum::INVOICE);
        $refunds = $invoices->where('type', InvoiceTypeEnum::REFUND);

        $stats['revenue_amount'] = $sales->sum('net_amount');
        $stats['lost_revenue_other_amount'] = abs($refunds->sum('net_amount'));
        $stats['lost_revenue_out_of_stock_amount'] = 0;
        $stats['lost_revenue_replacements_amount'] = 0;
        $stats['lost_revenue_compensations_amount'] = 0;

        $customer->stats()->update($stats);
    }
}
