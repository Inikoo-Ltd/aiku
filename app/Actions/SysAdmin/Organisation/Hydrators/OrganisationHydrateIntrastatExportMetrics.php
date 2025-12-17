<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:27:08 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Accounting\IntrastatExportMetrics;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateIntrastatExportMetrics implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:intrastat-export-metrics {organisation?} {--date=}';

    public function getJobUniqueId(Organisation $organisation, Carbon $date): string
    {
        return $organisation->id . '-' . $date->format('Ymd');
    }

    public function asCommand(Command $command): void
    {
        $organisationSlug = $command->argument('organisation');

        if ($organisationSlug) {
            $organisation = Organisation::where('slug', $organisationSlug)->first();
            if (!$organisation) {
                $command->error("Organisation not found: $organisationSlug");
                return;
            }

            $date = $command->option('date') ? Carbon::parse($command->option('date')) : Carbon::today();
            $this->handle($organisation, $date);
            $command->info("Hydrated Intrastat export metrics for $organisation->slug on {$date->toDateString()}");
        } else {
            Organisation::chunk(10, function ($organisations) use ($command) {
                foreach ($organisations as $org) {
                    $date = $command->option('date') ? Carbon::parse($command->option('date')) : Carbon::today();
                    $this->handle($org, $date);
                    $command->info("Hydrated Intrastat export metrics for $org->slug");
                }
            });
        }
    }

    public function handle(Organisation $organisation, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        IntrastatExportMetrics::where('organisation_id', $organisation->id)
            ->where('date', $dayStart)
            ->delete();

        $euCountryCodes = Country::getCountryCodesInEU();
        $euCountryIds = Country::whereIn('code', $euCountryCodes)
            ->where('id', '!=', $organisation->country_id)
            ->pluck('id')
            ->toArray();

        if (empty($euCountryIds)) {
            return;
        }

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
            ->whereBetween('dn.dispatched_at', [$dayStart, $dayEnd])
            ->select(
                'dn.id as delivery_note_id',
                'dn.type as delivery_note_type',
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

                $key = $tariffCode . '|' . 
                       $item->country_id . '|' . 
                       ($item->tax_category_id ?? 'null') . '|' .
                       ($item->delivery_note_type ?? 'null');

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
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

            IntrastatExportMetrics::create([
                'organisation_id' => $organisation->id,
                'date' => $dayStart,
                'tariff_code' => $data['tariff_code'],
                'country_id' => $data['country_id'],
                'tax_category_id' => $data['tax_category_id'],
                'delivery_note_type' => $data['delivery_note_type'],
                'quantity' => $data['quantity'],
                'value_org_currency' => $data['value'],
                'weight' => $data['weight'],
                'delivery_notes_count' => count($data['delivery_notes']),
                'products_count' => count($data['products']),
                'invoices_count' => count($data['invoices']),
                'partner_tax_numbers' => empty($partnerTaxNumbers) ? null : $partnerTaxNumbers,
                'valid_tax_numbers_count' => $validCount,
                'invalid_tax_numbers_count' => $invalidCount,
                'mode_of_transport' => IntrastatTransportModeEnum::ROAD,
                'delivery_terms' => IntrastatDeliveryTermsEnum::DAP,
                'nature_of_transaction' => $natureOfTransaction,
            ]);
        }
    }
}
