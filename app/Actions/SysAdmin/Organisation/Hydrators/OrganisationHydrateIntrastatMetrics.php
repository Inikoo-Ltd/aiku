<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:27:08 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Accounting\IntrastatMetrics;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateIntrastatMetrics implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:intrastat-metrics {organisation?} {--date=}';

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
            $command->info("Hydrated Intrastat metrics for $organisation->slug on {$date->toDateString()}");
        } else {
            // Hydrate all organisations
            Organisation::chunk(10, function ($organisations) use ($command) {
                foreach ($organisations as $org) {
                    $date = $command->option('date') ? Carbon::parse($command->option('date')) : Carbon::today();
                    $this->handle($org, $date);
                    $command->info("Hydrated Intrastat metrics for $org->slug");
                }
            });
        }
    }

    public function handle(Organisation $organisation, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        // Delete existing records for this date to avoid duplicates when splitting tariff codes
        IntrastatMetrics::where('organisation_id', $organisation->id)
            ->where('date', $dayStart)
            ->delete();

        // Get EU country IDs (excluding own country)
        $euCountryCodes = Country::getCountryCodesInEU();
        $euCountryIds = Country::whereIn('code', $euCountryCodes)
            ->where('id', '!=', $organisation->country_id)
            ->pluck('id')
            ->toArray();

        if (empty($euCountryIds)) {
            return;
        }

        // Get all delivery note items dispatched to EU countries on this date
        // Use subquery to get first product per org_stock to avoid duplicates from many-to-many
        $metrics = DB::table('delivery_note_items as dni')
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
            ->where('dn.organisation_id', $organisation->id)
            ->where('dn.state', DeliveryNoteStateEnum::DISPATCHED)
            ->whereIn('dn.delivery_country_id', $euCountryIds)
            ->whereBetween('dn.dispatched_at', [$dayStart, $dayEnd])
            ->select(
                'stock_products.tariff_code',
                'dn.delivery_country_id as country_id',
                't.tax_category_id',
                DB::raw('SUM(dni.quantity_dispatched) as total_quantity'),
                DB::raw('SUM(dni.org_revenue_amount) as total_value'),
                DB::raw('SUM(COALESCE(dni.estimated_picked_weight, 0)) as total_weight'),
                DB::raw('COUNT(DISTINCT dn.id) as delivery_notes_count'),
                DB::raw('COUNT(DISTINCT stock_products.product_id) as products_count')
            )
            ->groupBy('stock_products.tariff_code', 'dn.delivery_country_id', 't.tax_category_id')
            ->get();

        // Split comma-separated tariff codes and aggregate by individual code
        $aggregated = [];

        foreach ($metrics as $metric) {
            // Split comma-separated tariff codes
            $tariffCodes = array_map('trim', explode(',', $metric->tariff_code));

            foreach ($tariffCodes as $tariffCode) {
                if (empty($tariffCode)) {
                    continue;
                }

                // Normalize tariff code: remove all spaces
                $tariffCode = str_replace(' ', '', $tariffCode);

                if (empty($tariffCode)) {
                    continue;
                }

                // Create unique key for aggregation
                $key = $tariffCode . '|' . $metric->country_id . '|' . ($metric->tax_category_id ?? 'null');

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'tariff_code' => $tariffCode,
                        'country_id' => $metric->country_id,
                        'tax_category_id' => $metric->tax_category_id,
                        'quantity' => 0,
                        'value' => 0,
                        'weight' => 0,
                        'delivery_notes' => 0,
                        'products' => 0,
                    ];
                }

                // Aggregate metrics
                $aggregated[$key]['quantity'] += $metric->total_quantity ?? 0;
                $aggregated[$key]['value'] += $metric->total_value ?? 0;
                $aggregated[$key]['weight'] += $metric->total_weight ?? 0;
                $aggregated[$key]['delivery_notes'] += $metric->delivery_notes_count ?? 0;
                $aggregated[$key]['products'] += $metric->products_count ?? 0;
            }
        }

        // Create records from aggregated data
        foreach ($aggregated as $data) {
            IntrastatMetrics::create([
                'organisation_id' => $organisation->id,
                'date' => $dayStart,
                'tariff_code' => $data['tariff_code'],
                'country_id' => $data['country_id'],
                'tax_category_id' => $data['tax_category_id'],
                'quantity' => $data['quantity'],
                'value_org_currency' => $data['value'],
                'weight' => $data['weight'],
                'delivery_notes_count' => $data['delivery_notes'],
                'products_count' => $data['products'],
            ]);
        }
    }
}
