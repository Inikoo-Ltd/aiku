<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 19:27:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Search\InvoiceRecordSearch;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Accounting\InvoiceCategory\ProcessInvoiceCategoryTimeSeriesRecords;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Catalogue\Shop\ProcessShopTimeSeriesRecords;
use App\Actions\Comms\Email\SendInvoiceToFulfilmentCustomerEmail;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateRevenue;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateInvoices;
use App\Actions\Dropshipping\Platform\ProcessPlatformTimeSeriesRecords;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsInvoices;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSales;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSalesGrpCurrency;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSalesOrgCurrency;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Actions\Ordering\SalesChannel\ProcessSalesChannelTimeSeriesRecords;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\ProcessOrganisationTimeSeriesRecords;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RunInvoiceHydrators
{
    use asAction;

    public function handle(Invoice $invoice, int $hydratorsDelay = 0): void
    {

        $this->runImportantJobs($invoice, $hydratorsDelay, async: true);
        $this->runAnotherJobs($invoice, $hydratorsDelay);

    }

    public function runImportantJobs(Invoice $invoice, int $hydratorsDelay, bool $async = false): void
    {

        if ($invoice->customer_id) {
            CustomerHydrateInvoices::dispatch($invoice->customer_id);
        }

        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        // Helper internal
        $queueOrRun = function ($job, array $params = []) use ($async, $hydratorsDelay) {
            if ($async) {
                return $job::dispatch(...$params)->delay($hydratorsDelay);
            }

            return $job::run(...$params);
        };

        // --- Basic Hydrators ---
        $queueOrRun(ShopHydrateInvoices::class, [$invoice->shop]);
        $queueOrRun(OrganisationHydrateInvoices::class, [$invoice->organisation]);
        $queueOrRun(GroupHydrateInvoices::class, [$invoice->group]);

        // --- Basic Time Series ---
        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $queueOrRun(ProcessShopTimeSeriesRecords::class, [
                $invoice->shop_id,
                $frequency,
                match ($frequency) {
                    TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                    TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                    TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                    TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                    TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                },
                now()->toDateString(),
            ]);

            $queueOrRun(ProcessOrganisationTimeSeriesRecords::class, [
                $invoice->organisation_id,
                $frequency,
                match ($frequency) {
                    TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                    TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                    TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                    TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                    TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                },
                now()->toDateString(),
            ]);
        }

        // --- Invoice Category ---
        if ($invoice->invoiceCategory) {
            $queueOrRun(InvoiceCategoryHydrateInvoices::class, [$invoice->invoiceCategory]);
            $queueOrRun(InvoiceCategoryHydrateSalesIntervals::class, [$invoice->invoiceCategory, $intervalsExceptHistorical, []]);
            $queueOrRun(InvoiceCategoryHydrateOrderingIntervals::class, [$invoice->invoiceCategory, $intervalsExceptHistorical, []]);

            // --- Invoice Category Time Series ---
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $queueOrRun(ProcessInvoiceCategoryTimeSeriesRecords::class, [
                    $invoice->invoice_category_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString(),
                ]);
            }
        }

        // --- Sales Intervals ---
        $queueOrRun(ShopHydrateSalesIntervals::class, [$invoice->shop, $intervalsExceptHistorical, []]);
        $queueOrRun(OrganisationHydrateSalesIntervals::class, [$invoice->organisation, $intervalsExceptHistorical, []]);
        $queueOrRun(GroupHydrateSalesIntervals::class, [$invoice->group, $intervalsExceptHistorical, []]);

        // --- Master shop ---
        if ($invoice->master_shop_id) {
            $queueOrRun(MasterShopHydrateSalesIntervals::class, [$invoice->master_shop_id, $intervalsExceptHistorical, []]);
            $queueOrRun(MasterShopHydrateInvoiceIntervals::class, [$invoice->master_shop_id, $intervalsExceptHistorical, []]);
        }

        // --- Invoice Intervals ---
        $queueOrRun(ShopHydrateInvoiceIntervals::class, [$invoice->shop, $intervalsExceptHistorical, []]);
        $queueOrRun(OrganisationHydrateInvoiceIntervals::class, [$invoice->organisation, $intervalsExceptHistorical, []]);
        $queueOrRun(GroupHydrateInvoiceIntervals::class, [$invoice->group, $intervalsExceptHistorical, []]);

        // --- Platform intervals ---
        if ($invoice->platform_id) {
            $queueOrRun(ShopHydratePlatformSalesIntervalsInvoices::class, [$invoice->shop_id, $invoice->platform_id, $intervalsExceptHistorical, []]);
            $queueOrRun(ShopHydratePlatformSalesIntervalsSales::class, [$invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, []]);
            $queueOrRun(ShopHydratePlatformSalesIntervalsSalesOrgCurrency::class, [$invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, []]);
            $queueOrRun(ShopHydratePlatformSalesIntervalsSalesGrpCurrency::class, [$invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, []]);

            // --- Platform Time Series ---
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $queueOrRun(ProcessPlatformTimeSeriesRecords::class, [
                    $invoice->platform_id,
                    $invoice->shop_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString(),
                ]);
            }
        }

        // --- Sales Channel Time Series ---
        if ($invoice->sales_channel_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $queueOrRun(ProcessSalesChannelTimeSeriesRecords::class, [
                    $invoice->sales_channel_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString(),
                ]);
            }
        }

        if ($invoice->shop->type == ShopTypeEnum::FULFILMENT) {
            $queueOrRun(SendInvoiceToFulfilmentCustomerEmail::class, [$invoice]);
        }
    }

    public function runAnotherJobs(Invoice $invoice, int $hydratorsDelay): void
    {

        if ($invoice->customer_client_id) {
            CustomerClientHydrateInvoices::dispatch($invoice->customerClient);
        }

        if ($invoice->shipping_zone_id) {
            ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($hydratorsDelay);
        }
        if ($invoice->shipping_zone_schema_id) {
            ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($hydratorsDelay);
        }

        CustomerHydrateClv::dispatch($invoice->customer_id)->delay($hydratorsDelay);
        CustomerHydrateRevenue::dispatch($invoice->customer_id)->delay($hydratorsDelay);

        InvoiceRecordSearch::dispatch($invoice);
    }

    public function getCommandSignature(): string
    {
        return 'accounting:invoice:run-hydrators {invoice}';
    }

    public function asCommand(Command $command): int
    {
        $invoice = Invoice::where('slug', $command->argument('invoice'))->firstOrFail();
        $this->handle($invoice);

        return 0;
    }
}
