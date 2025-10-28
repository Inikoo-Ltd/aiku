<?php

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $commandSignature = 'hydrate:platform-sales-intervals {platform}';

    public function getJobUniqueId(Platform $platform, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($platform, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('slug', $command->argument('platform'))->first();

        $this->handle($platform);
    }

    // Note: Experimental Data (Need to be checked)
    public function handle(Platform $platform, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $invoiceQueryBase = Invoice
            ::where('in_process', false)
            ->where('platform_id', $platform->id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->selectRaw('count(*) as  sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $invoiceQueryBase,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $newChannelsQueryBase = CustomerSalesChannel
            ::where('platform_id', $platform->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->selectRaw('count(*) as  sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $newChannelsQueryBase,
            statField: 'new_channels_',
            dateField: 'created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $newCustomersQueryBase = CustomerSalesChannel
            ::leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->where('platform_id', $platform->id)
            ->selectRaw('count(distinct customer_sales_channels.customer_id) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $newCustomersQueryBase,
            statField: 'new_customers_',
            dateField: 'customer_sales_channels.created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        // TODO: fill the new_portfolios

        // TODO: fill the new_customer_client

        // TODO: fill the sales_grp_currency

        return;
    }
}
