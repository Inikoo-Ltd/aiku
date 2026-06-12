<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\ProcessInvoiceCategoryTimeSeriesRecords;
use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Catalogue\CollectionTimeSeries\PreprocessCollectionTimeSeries;
use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Actions\Catalogue\Shop\ProcessShopTimeSeriesRecords;
use App\Actions\CRM\Customer\ProcessCustomerTimeSeriesRecords;
use App\Actions\Discounts\Offer\ProcessOfferTimeSeriesRecords;
use App\Actions\Discounts\OfferCampaign\ProcessOfferCampaignTimeSeriesRecords;
use App\Actions\Dropshipping\Platform\ProcessPlatformTimeSeriesRecords;
use App\Actions\Goods\Stock\ProcessStockTimeSeriesRecords;
use App\Actions\Goods\StockFamily\ProcessStockFamilyTimeSeriesRecords;
use App\Actions\Goods\TradeUnit\ProcessTradeUnitTimeSeriesRecords;
use App\Actions\Goods\TradeUnitFamily\ProcessTradeUnitFamilyTimeSeriesRecords;
use App\Actions\Helpers\Brand\ProcessBrandTimeSeriesRecords;
use App\Actions\Inventory\OrgStock\ProcessOrgStockTimeSeriesRecords;
use App\Actions\Inventory\OrgStockFamily\ProcessOrgStockFamilyTimeSeriesRecords;
use App\Actions\Masters\MasterAssetTimeSeries\ProcessMasterAssetTimeSeriesRecords;
use App\Actions\Masters\MasterCollectionTimeSeries\PreprocessMasterCollectionTimeSeries;
use App\Actions\Masters\MasterProductCategoryTimeSeries\ProcessMasterProductCategoryTimeSeriesRecords;
use App\Actions\Masters\MasterShop\ProcessMasterShopTimeSeriesRecords;
use App\Actions\Ordering\SalesChannel\ProcessSalesChannelTimeSeriesRecords;
use App\Actions\SysAdmin\Organisation\ProcessOrganisationTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RedoDailyInvoiceTimeSeries
{
    use AsAction;

    public string $jobQueue = 'default-long-slave';

    public function handle(?string $date = null): void
    {
        $today       = $date ?? now()->toDateString();
        $periodDates = $this->resolvePeriodDates($today);

        $this->dispatchInvoiceBasedTimeSeries($today, $periodDates);
        $this->dispatchInvoiceTransactionBasedTimeSeries($today, $periodDates);
    }

    protected function resolvePeriodDates(string $date): array
    {
        $carbon = Carbon::parse($date);
        $dates  = [];

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $dates[$frequency->value] = [
                'from' => match ($frequency) {
                    TimeSeriesFrequencyEnum::YEARLY    => $carbon->copy()->startOfYear()->toDateString(),
                    TimeSeriesFrequencyEnum::QUARTERLY => $carbon->copy()->startOfQuarter()->toDateString(),
                    TimeSeriesFrequencyEnum::MONTHLY   => $carbon->copy()->startOfMonth()->toDateString(),
                    TimeSeriesFrequencyEnum::WEEKLY    => $carbon->copy()->startOfWeek()->toDateString(),
                    TimeSeriesFrequencyEnum::DAILY     => $date,
                },
                'to'   => $date,
            ];
        }

        return $dates;
    }

    protected function dispatchInvoiceBasedTimeSeries(string $today, array $periodDates): void
    {
        $base = DB::connection('aiku_no_sticky')->table('invoices')
            ->whereDate('date', $today)
            ->where('in_process', false)
            ->whereNull('deleted_at');

        (clone $base)->distinct()->pluck('shop_id')->filter()->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessShopTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->distinct()->pluck('organisation_id')->filter()->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessOrganisationTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->whereNotNull('invoice_category_id')->distinct()->pluck('invoice_category_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessInvoiceCategoryTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->whereNotNull('sales_channel_id')->distinct()->pluck('sales_channel_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessSalesChannelTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->whereNotNull('master_shop_id')->distinct()->pluck('master_shop_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessMasterShopTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->whereNotNull('customer_id')->distinct()->pluck('customer_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessCustomerTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $base)->whereNotNull('platform_id')->distinct()->select('platform_id', 'shop_id')->get()
            ->each(function ($row) use ($periodDates) {
                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessPlatformTimeSeriesRecords::dispatch(
                        $row->platform_id,
                        $row->shop_id,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $periodDates[$frequency->value]['to']
                    );
                }
            });
    }

    protected function dispatchInvoiceTransactionBasedTimeSeries(string $today, array $periodDates): void
    {
        $base = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->whereDate('date', $today)
            ->whereNull('deleted_at');

        (clone $base)->whereNotNull('asset_id')->distinct()->pluck('asset_id')->each(function ($id) use ($periodDates, $today) {
            $this->dispatchForAllFrequencies(ProcessAssetTimeSeriesRecords::class, $id, $periodDates);
            PreprocessCollectionTimeSeries::dispatch($id, $today)->delay(30);
        });

        foreach (['department_id', 'family_id', 'sub_department_id'] as $column) {
            (clone $base)->whereNotNull($column)->distinct()->pluck($column)->each(
                fn ($id) => $this->dispatchForAllFrequencies(ProcessProductCategoryTimeSeriesRecords::class, $id, $periodDates)
            );
        }

        (clone $base)->whereNotNull('master_asset_id')->distinct()->pluck('master_asset_id')->each(function ($id) use ($periodDates, $today) {
            $this->dispatchForAllFrequencies(ProcessMasterAssetTimeSeriesRecords::class, $id, $periodDates);
            PreprocessMasterCollectionTimeSeries::dispatch($id, $today)->delay(30);
        });

        foreach (['master_department_id', 'master_family_id', 'master_sub_department_id'] as $column) {
            (clone $base)->whereNotNull($column)->distinct()->pluck($column)->each(
                fn ($id) => $this->dispatchForAllFrequencies(ProcessMasterProductCategoryTimeSeriesRecords::class, $id, $periodDates)
            );
        }

        (clone $base)->whereNotNull('brand_id')->whereNotNull('shop_id')
            ->distinct()->select('brand_id', 'shop_id')->get()
            ->each(function ($row) use ($periodDates) {
                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessBrandTimeSeriesRecords::dispatch(
                        $row->brand_id,
                        $row->shop_id,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $periodDates[$frequency->value]['to']
                    );
                }
            });

        $stockPivotBase = DB::connection('aiku_no_sticky')->table('invoice_transaction_has_stocks as pivot')
            ->join('invoice_transactions', 'invoice_transactions.id', '=', 'pivot.invoice_transaction_id')
            ->whereDate('invoice_transactions.date', $today)
            ->whereNull('invoice_transactions.deleted_at');

        (clone $stockPivotBase)->whereNotNull('pivot.stock_id')->distinct()->pluck('pivot.stock_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessStockTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $stockPivotBase)->whereNotNull('pivot.stock_family_id')->distinct()->pluck('pivot.stock_family_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessStockFamilyTimeSeriesRecords::class, $id, $periodDates)
        );

        $orgStockPivotBase = DB::connection('aiku_no_sticky')->table('invoice_transaction_has_org_stocks as pivot')
            ->join('invoice_transactions', 'invoice_transactions.id', '=', 'pivot.invoice_transaction_id')
            ->whereDate('invoice_transactions.date', $today)
            ->whereNull('invoice_transactions.deleted_at');

        (clone $orgStockPivotBase)->whereNotNull('pivot.org_stock_id')->distinct()->pluck('pivot.org_stock_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessOrgStockTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $orgStockPivotBase)->whereNotNull('pivot.org_stock_family_id')->distinct()->pluck('pivot.org_stock_family_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessOrgStockFamilyTimeSeriesRecords::class, $id, $periodDates)
        );

        $tradeUnitPivotBase = DB::connection('aiku_no_sticky')->table('invoice_transaction_has_trade_units as pivot')
            ->join('invoice_transactions', 'invoice_transactions.id', '=', 'pivot.invoice_transaction_id')
            ->whereDate('invoice_transactions.date', $today)
            ->whereNull('invoice_transactions.deleted_at');

        (clone $tradeUnitPivotBase)->whereNotNull('pivot.trade_unit_id')->distinct()->pluck('pivot.trade_unit_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessTradeUnitTimeSeriesRecords::class, $id, $periodDates)
        );

        (clone $tradeUnitPivotBase)->whereNotNull('pivot.trade_unit_family_id')->distinct()->pluck('pivot.trade_unit_family_id')->each(
            fn ($id) => $this->dispatchForAllFrequencies(ProcessTradeUnitFamilyTimeSeriesRecords::class, $id, $periodDates)
        );

        $offerBase = DB::connection('aiku_no_sticky')->table('transaction_has_offer_allowances')
            ->join('invoice_transactions', 'invoice_transactions.transaction_id', '=', 'transaction_has_offer_allowances.transaction_id')
            ->whereDate('invoice_transactions.date', $today)
            ->whereNull('invoice_transactions.deleted_at');

        (clone $offerBase)->whereNotNull('transaction_has_offer_allowances.offer_id')
            ->distinct()->pluck('transaction_has_offer_allowances.offer_id')->each(
                fn ($id) => $this->dispatchForAllFrequencies(ProcessOfferTimeSeriesRecords::class, $id, $periodDates)
            );

        (clone $offerBase)->whereNotNull('transaction_has_offer_allowances.offer_campaign_id')
            ->distinct()->pluck('transaction_has_offer_allowances.offer_campaign_id')->each(
                fn ($id) => $this->dispatchForAllFrequencies(ProcessOfferCampaignTimeSeriesRecords::class, $id, $periodDates)
            );
    }

    protected function dispatchForAllFrequencies(string $processClass, int $id, array $periodDates): void
    {
        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $processClass::dispatch(
                $id,
                $frequency,
                $periodDates[$frequency->value]['from'],
                $periodDates[$frequency->value]['to']
            );
        }
    }
}
