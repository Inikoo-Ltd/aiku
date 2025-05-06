<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\CustomerClient\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateInvoices;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerClientHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateInvoices;

    public function getJobUniqueId(CustomerClient $customerClient): string
    {
        return $customerClient->id;
    }

    public function handle(CustomerClient $customerClient): void
    {
        $stats = [
            'invoices_amount' => $customerClient->invoices->sum('total_amount'),
        ];

        $stats = array_merge($stats, $this->getInvoicesStats($customerClient));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'invoices',
            field: 'type',
            enum: InvoiceTypeEnum::class,
            models: Invoice::class,
            where: function ($q) use ($customerClient) {
                $q->where('customer_client_id', $customerClient->id);
            }
        ));

        $customerClient->stats()->update($stats);
    }

}
