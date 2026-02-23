<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:20:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\IntrastatExportTimeSeries;

use App\Actions\Accounting\IntrastatExportTimeSeries\Hydrators\IntrastatExportTimeSeriesHydrateNumberRecords;
use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\IntrastatExportTimeSeries;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessIntrastatExportTimeSeriesRecords implements ShouldBeUnique
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
        // First, get raw metrics with all metadata needed for aggregation
        $rawMetrics = DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
            ->joinSub(
                DB::table('product_has_org_stocks as phos')
                    ->join('products as p', 'p.id', '=', 'phos.product_id')
                    ->select(
                        'phos.org_stock_id',
                        DB::raw('MIN(p.id) as product_id'),
                        DB::raw('MIN(p.tariff_code) as tariff_code')
                    )
                    ->whereNotNull('p.tariff_code')
                    ->where('p.tariff_code', '!=', '')
                    ->groupBy('phos.org_stock_id'),
                'stock_products',
                'stock_products.org_stock_id',
                '=',
                'dni.org_stock_id'
            )
            ->leftJoin('transactions as t', 't.id', '=', 'dni.transaction_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 't.invoice_id')
            ->where('dn.organisation_id', $organisation->id)
            ->where('dn.state', DeliveryNoteStateEnum::DISPATCHED)
            ->whereIn('dn.delivery_country_id', $euCountryIds)
            ->whereBetween('dn.dispatched_at', [$from, $to])
            ->select(
                'dn.id as delivery_note_id',
                'dn.type as delivery_note_type',
                'dn.dispatched_at',
                'stock_products.tariff_code',
                'dn.delivery_country_id as country_id',
                't.tax_category_id',
                'inv.id as invoice_id',
                'inv.tax_number',
                'inv.tax_number_valid',
                'dni.quantity_dispatched',
                'dni.org_revenue_amount',
                DB::raw('COALESCE(dni.estimated_picked_weight, 0) as item_weight'),
                'stock_products.product_id'
            )
            ->get();

        // Aggregate by time period, tariff code, country, tax category, and delivery note type
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
                $dispatchedAt = Carbon::parse($item->dispatched_at);

                if ($frequency == TimeSeriesFrequencyEnum::YEARLY) {
                    $periodKey = $dispatchedAt->year;
                    $periodFrom = Carbon::create($dispatchedAt->year, 1, 1)->startOfDay();
                    $periodTo = Carbon::create($dispatchedAt->year, 12, 31)->endOfDay();
                    $period = (string) $dispatchedAt->year;
                } elseif ($frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                    $quarter = $dispatchedAt->quarter;
                    $periodKey = $dispatchedAt->year . '-Q' . $quarter;
                    $periodFrom = Carbon::create($dispatchedAt->year, ($quarter - 1) * 3 + 1, 1)->startOfQuarter();
                    $periodTo = Carbon::create($dispatchedAt->year, ($quarter - 1) * 3 + 1, 1)->endOfQuarter();
                    $period = $dispatchedAt->year . ' Q' . $quarter;
                } elseif ($frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                    $periodKey = $dispatchedAt->format('Y-m');
                    $periodFrom = Carbon::create($dispatchedAt->year, $dispatchedAt->month, 1)->startOfMonth();
                    $periodTo = Carbon::create($dispatchedAt->year, $dispatchedAt->month, 1)->endOfMonth();
                    $period = $dispatchedAt->format('Y-m');
                } elseif ($frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                    $week = $dispatchedAt->week;
                    $periodKey = $dispatchedAt->year . '-W' . $week;
                    $periodFrom = Carbon::create($dispatchedAt->year)->week($week)->startOfWeek();
                    $periodTo = Carbon::create($dispatchedAt->year)->week($week)->endOfWeek();
                    $period = $dispatchedAt->year . ' W' . str_pad($week, 2, '0', STR_PAD_LEFT);
                } else { // DAILY
                    $periodKey = $dispatchedAt->format('Y-m-d');
                    $periodFrom = $dispatchedAt->copy()->startOfDay();
                    $periodTo = $dispatchedAt->copy()->endOfDay();
                    $period = $dispatchedAt->format('Y-m-d');
                }

                $key = $periodKey . '|' .
                       $tariffCode . '|' .
                       $item->country_id . '|' .
                       ($item->tax_category_id ?? 'null') . '|' .
                       ($item->delivery_note_type ?? 'null');

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'period' => $period,
                        'period_from' => $periodFrom,
                        'period_to' => $periodTo,
                        'tariff_code' => $tariffCode,
                        'country_id' => $item->country_id,
                        'tax_category_id' => $item->tax_category_id,
                        'delivery_note_type' => $item->delivery_note_type,
                        'quantity' => 0,
                        'value' => 0,
                        'weight' => 0,
                        'delivery_notes' => [],
                        'products' => [],
                        'invoices' => [],
                        'tax_numbers' => [],
                    ];
                }

                $aggregated[$key]['quantity'] += $item->quantity_dispatched ?? 0;
                $aggregated[$key]['value'] += $item->org_revenue_amount ?? 0;
                $aggregated[$key]['weight'] += $item->item_weight ?? 0;
                $aggregated[$key]['delivery_notes'][$item->delivery_note_id] = true;
                $aggregated[$key]['products'][$item->product_id] = true;

                if ($item->invoice_id) {
                    $aggregated[$key]['invoices'][$item->invoice_id] = true;

                    if ($item->tax_number) {
                        $taxNumberKey = $item->tax_number . '_' . ($item->tax_number_valid ? 'valid' : 'invalid');
                        if (!isset($aggregated[$key]['tax_numbers'][$taxNumberKey])) {
                            $aggregated[$key]['tax_numbers'][$taxNumberKey] = [
                                'number' => $item->tax_number,
                                'valid' => (bool) $item->tax_number_valid,
                            ];
                        }
                    }
                }
            }
        }

        // Now save aggregated records
        foreach ($aggregated as $data) {
            $partnerTaxNumbers = array_values($data['tax_numbers']);
            $validCount = 0;
            $invalidCount = 0;

            foreach ($partnerTaxNumbers as $taxNumber) {
                if ($taxNumber['valid']) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
            }

            $natureOfTransaction = match($data['delivery_note_type']) {
                DeliveryNoteTypeEnum::REPLACEMENT->value => IntrastatNatureOfTransactionEnum::RETURN_REPLACEMENT,
                default => IntrastatNatureOfTransactionEnum::OUTRIGHT_PURCHASE,
            };

            $timeSeries = IntrastatExportTimeSeries::firstOrCreate(
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
                    'intrastat_export_time_series_id' => $timeSeries->id,
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
                    'delivery_notes_count'      => count($data['delivery_notes']),
                    'products_count'            => count($data['products']),
                    'delivery_note_type'        => $data['delivery_note_type'],
                    'invoices_count'            => count($data['invoices']),
                    'partner_tax_numbers'       => empty($partnerTaxNumbers) ? null : $partnerTaxNumbers,
                    'valid_tax_numbers_count'   => $validCount,
                    'invalid_tax_numbers_count' => $invalidCount,
                    'mode_of_transport'         => IntrastatTransportModeEnum::ROAD,
                    'delivery_terms'            => IntrastatDeliveryTermsEnum::DAP,
                    'nature_of_transaction'     => $natureOfTransaction,
                ]
            );

            IntrastatExportTimeSeriesHydrateNumberRecords::run($timeSeries->id);
        }
    }
}
