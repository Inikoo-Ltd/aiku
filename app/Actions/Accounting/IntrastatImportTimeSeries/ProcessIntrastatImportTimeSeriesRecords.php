<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:24:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\IntrastatImportTimeSeries;

use App\Actions\Accounting\IntrastatImportTimeSeries\Hydrators\IntrastatImportTimeSeriesHydrateNumberRecords;
use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\IntrastatImportTimeSeries;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessIntrastatImportTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $organisationId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$organisationId:$frequency->value:$from:$to";
    }

    public function handle(int $organisationId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $organisation = Organisation::find($organisationId);

        if (!$organisation) {
            return;
        }

        $euCountryCodes = Country::getCountryCodesInEU();
        $euCountryIds = Country::whereIn('code', $euCountryCodes)
            ->where('id', '!=', $organisation->country_id)
            ->pluck('id')
            ->toArray();

        if (empty($euCountryIds)) {
            return;
        }

        $this->processTimeSeries($organisation, $euCountryIds, $frequency, $from, $to);
    }

    protected function processTimeSeries(Organisation $organisation, array $euCountryIds, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        // Query for OrgSupplier deliveries
        $supplierMetrics = DB::table('stock_delivery_items as sdi')
            ->join('stock_deliveries as sd', 'sd.id', '=', 'sdi.stock_delivery_id')
            ->joinSub(
                DB::table('product_has_org_stocks as phos')
                    ->join('products as p', 'p.id', '=', 'phos.product_id')
                    ->select(
                        'phos.org_stock_id',
                        DB::raw('MIN(p.id) as product_id'),
                        DB::raw('MIN(p.tariff_code) as tariff_code'),
                        DB::raw('MIN(COALESCE(p.gross_weight, 0)) as product_weight')
                    )
                    ->whereNotNull('p.tariff_code')
                    ->where('p.tariff_code', '!=', '')
                    ->groupBy('phos.org_stock_id'),
                'stock_products',
                'stock_products.org_stock_id',
                '=',
                'sdi.org_stock_id'
            )
            ->join('org_suppliers as osup', 'osup.id', '=', 'sd.parent_id')
            ->join('suppliers as sup', 'sup.id', '=', 'osup.supplier_id')
            ->leftJoin('addresses as addr', 'addr.id', '=', 'sup.address_id')
            ->where('sd.organisation_id', $organisation->id)
            ->where('sd.state', StockDeliveryStateEnum::CHECKED)
            ->where('sd.parent_type', 'OrgSupplier')
            ->whereIn('addr.country_id', $euCountryIds)
            ->whereNotNull('sd.checked_at')
            ->whereBetween('sd.checked_at', [$from, $to])
            ->where('sdi.unit_quantity', '>', 0)
            ->select(
                'sd.id as stock_delivery_id',
                'sd.checked_at',
                'stock_products.tariff_code',
                'addr.country_id',
                'sdi.unit_quantity',
                'sdi.org_net_amount',
                'stock_products.product_id',
                'stock_products.product_weight'
            );

        // Query for OrgPartner deliveries (from partner organisations)
        $partnerMetrics = DB::table('stock_delivery_items as sdi')
            ->join('stock_deliveries as sd', 'sd.id', '=', 'sdi.stock_delivery_id')
            ->joinSub(
                DB::table('product_has_org_stocks as phos')
                    ->join('products as p', 'p.id', '=', 'phos.product_id')
                    ->select(
                        'phos.org_stock_id',
                        DB::raw('MIN(p.id) as product_id'),
                        DB::raw('MIN(p.tariff_code) as tariff_code'),
                        DB::raw('MIN(COALESCE(p.gross_weight, 0)) as product_weight')
                    )
                    ->whereNotNull('p.tariff_code')
                    ->where('p.tariff_code', '!=', '')
                    ->groupBy('phos.org_stock_id'),
                'stock_products',
                'stock_products.org_stock_id',
                '=',
                'sdi.org_stock_id'
            )
            ->join('org_partners as opar', 'opar.id', '=', 'sd.parent_id')
            ->join('organisations as partner_org', 'partner_org.id', '=', 'opar.partner_id')
            ->where('sd.organisation_id', $organisation->id)
            ->where('sd.state', StockDeliveryStateEnum::CHECKED)
            ->where('sd.parent_type', 'OrgPartner')
            ->whereIn('partner_org.country_id', $euCountryIds)
            ->whereNotNull('sd.checked_at')
            ->whereBetween('sd.checked_at', [$from, $to])
            ->where('sdi.unit_quantity', '>', 0)
            ->select(
                'sd.id as stock_delivery_id',
                'sd.checked_at',
                'stock_products.tariff_code',
                'partner_org.country_id as country_id',
                'sdi.unit_quantity',
                'sdi.org_net_amount',
                'stock_products.product_id',
                'stock_products.product_weight'
            );

        // Combine both queries
        $rawMetrics = $supplierMetrics->union($partnerMetrics)->get();

        $aggregated = [];

        foreach ($rawMetrics as $item) {
            $tariffCodes = array_map('trim', explode(',', $item->tariff_code));

            foreach ($tariffCodes as $tariffCode) {
                if (empty($tariffCode)) {
                    continue;
                }

                $tariffCode = str_replace(' ', '', $tariffCode);

                if (empty($tariffCode)) {
                    continue;
                }

                // Determine period based on frequency
                $checkedAt = Carbon::parse($item->checked_at);

                if ($frequency == TimeSeriesFrequencyEnum::YEARLY) {
                    $periodKey = $checkedAt->year;
                    $periodFrom = Carbon::create($checkedAt->year, 1, 1)->startOfDay();
                    $periodTo = Carbon::create($checkedAt->year, 12, 31)->endOfDay();
                    $period = (string) $checkedAt->year;
                } elseif ($frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                    $quarter = $checkedAt->quarter;
                    $periodKey = $checkedAt->year . '-Q' . $quarter;
                    $periodFrom = Carbon::create($checkedAt->year, ($quarter - 1) * 3 + 1, 1)->startOfQuarter();
                    $periodTo = Carbon::create($checkedAt->year, ($quarter - 1) * 3 + 1, 1)->endOfQuarter();
                    $period = $checkedAt->year . ' Q' . $quarter;
                } elseif ($frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                    $periodKey = $checkedAt->format('Y-m');
                    $periodFrom = Carbon::create($checkedAt->year, $checkedAt->month, 1)->startOfMonth();
                    $periodTo = Carbon::create($checkedAt->year, $checkedAt->month, 1)->endOfMonth();
                    $period = $checkedAt->format('Y-m');
                } elseif ($frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                    $week = $checkedAt->week;
                    $periodKey = $checkedAt->year . '-W' . $week;
                    $periodFrom = Carbon::create($checkedAt->year)->week($week)->startOfWeek();
                    $periodTo = Carbon::create($checkedAt->year)->week($week)->endOfWeek();
                    $period = $checkedAt->year . ' W' . str_pad($week, 2, '0', STR_PAD_LEFT);
                } else { // DAILY
                    $periodKey = $checkedAt->format('Y-m-d');
                    $periodFrom = $checkedAt->copy()->startOfDay();
                    $periodTo = $checkedAt->copy()->endOfDay();
                    $period = $checkedAt->format('Y-m-d');
                }

                $key = $periodKey . '|' .
                       $tariffCode . '|' .
                       $item->country_id . '|' .
                       'null';

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'period' => $period,
                        'period_from' => $periodFrom,
                        'period_to' => $periodTo,
                        'tariff_code' => $tariffCode,
                        'country_id' => $item->country_id,
                        'tax_category_id' => null,
                        'quantity' => 0,
                        'value' => 0,
                        'weight' => 0,
                        'stock_deliveries' => [],
                        'parts' => [],
                    ];
                }

                $aggregated[$key]['quantity'] += $item->unit_quantity ?? 0;
                $aggregated[$key]['value'] += $item->org_net_amount ?? 0;
                $aggregated[$key]['weight'] += ($item->unit_quantity ?? 0) * ($item->product_weight ?? 0);
                $aggregated[$key]['stock_deliveries'][$item->stock_delivery_id] = true;
                $aggregated[$key]['parts'][$item->product_id] = true;
            }
        }

        foreach ($aggregated as $data) {
            $timeSeries = IntrastatImportTimeSeries::firstOrCreate(
                [
                    'organisation_id' => $organisation->id,
                    'tariff_code' => $data['tariff_code'],
                    'country_id' => $data['country_id'],
                    'tax_category_id' => $data['tax_category_id'],
                    'frequency' => $frequency,
                ],
                [
                    'from' => null,
                    'to' => null,
                ]
            );

            $timeSeries->records()->updateOrCreate(
                [
                    'intrastat_import_time_series_id' => $timeSeries->id,
                    'period'                          => $data['period'],
                    'frequency'                       => $frequency->singleLetter(),
                ],
                [
                    'organisation_id'           => $organisation->id,
                    'from'                      => $data['period_from'],
                    'to'                        => $data['period_to'],
                    'quantity'                  => $data['quantity'],
                    'value_org_currency'        => $data['value'],
                    'weight'                    => $data['weight'],
                    'supplier_deliveries_count' => count($data['stock_deliveries']),
                    'parts_count'               => count($data['parts']),
                    'invoices_count'            => 0,
                    'supplier_tax_numbers'      => null,
                    'valid_tax_numbers_count'   => 0,
                    'invalid_tax_numbers_count' => 0,
                    'mode_of_transport'         => IntrastatTransportModeEnum::ROAD,
                    'delivery_terms'            => IntrastatDeliveryTermsEnum::DAP,
                    'nature_of_transaction'     => IntrastatNatureOfTransactionEnum::OUTRIGHT_PURCHASE,
                ]
            );

            IntrastatImportTimeSeriesHydrateNumberRecords::run($timeSeries->id);
        }
    }
}
