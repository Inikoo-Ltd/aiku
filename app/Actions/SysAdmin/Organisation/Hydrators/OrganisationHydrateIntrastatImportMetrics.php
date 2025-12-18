<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 00:31:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Accounting\IntrastatImportMetrics;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/*
 * NOTE: Supplier Tax Number Tracking
 *
 * Currently, supplier_tax_numbers is set to NULL because the 'suppliers' table
 * does not have 'tax_number' and 'tax_number_valid' columns.
 *
 * For Intrastat Import reporting (especially for Slovakia XML format), we need
 * to track supplier VAT numbers to comply with EU regulations.
 *
 * TODO: Add the following columns to 'suppliers' table:
 * - tax_number (string, nullable) - Supplier's VAT/Tax identification number
 * - tax_number_valid (boolean, default false) - Validation status of tax number
 *
 * Once these columns are added, update this hydrator to:
 * 1. Join suppliers table and select tax_number fields
 * 2. Aggregate unique supplier tax numbers per metric (similar to export metrics)
 * 3. Count valid and invalid tax numbers
 *
 * Reference: See OrganisationHydrateIntrastatExportMetrics for implementation example
 * (exports get tax_number from invoices.tax_number)
 */

class OrganisationHydrateIntrastatImportMetrics implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:intrastat-import-metrics {organisation?} {--date=}';

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
            $command->info("Hydrated Intrastat import metrics for $organisation->slug on {$date->toDateString()}");
        } else {
            Organisation::chunk(10, function ($organisations) use ($command) {
                foreach ($organisations as $org) {
                    $date = $command->option('date') ? Carbon::parse($command->option('date')) : Carbon::today();
                    $this->handle($org, $date);
                    $command->info("Hydrated Intrastat import metrics for $org->slug");
                }
            });
        }
    }

    public function handle(Organisation $organisation, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        IntrastatImportMetrics::where('organisation_id', $organisation->id)
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

        $rawMetrics = DB::table('stock_delivery_items as sdi')
            ->join('stock_deliveries as sd', 'sd.id', '=', 'sdi.stock_delivery_id')
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
                'sdi.org_stock_id'
            )
            ->join('org_suppliers as osup', function ($join) {
                $join->on('osup.id', '=', DB::raw("CASE 
                    WHEN sd.parent_type = 'OrgSupplier' THEN sd.parent_id 
                    ELSE NULL 
                END"));
            })
            ->leftJoin('suppliers as sup', 'sup.id', '=', 'osup.supplier_id')
            ->leftJoin('addresses as addr', 'addr.id', '=', 'sup.address_id')
            ->where('sd.organisation_id', $organisation->id)
            ->where('sd.state', StockDeliveryStateEnum::CHECKED)
            ->whereIn('addr.country_id', $euCountryIds)
            ->whereBetween('sd.checked_at', [$dayStart, $dayEnd])
            ->select(
                'sd.id as stock_delivery_id',
                'stock_products.tariff_code',
                'addr.country_id',
                'sdi.unit_quantity',
                'sdi.org_net_amount',
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
                       'null';

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
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
                $aggregated[$key]['stock_deliveries'][$item->stock_delivery_id] = true;
                $aggregated[$key]['parts'][$item->product_id] = true;
            }
        }

        foreach ($aggregated as $data) {

            IntrastatImportMetrics::create([
                'organisation_id' => $organisation->id,
                'date' => $dayStart,
                'tariff_code' => $data['tariff_code'],
                'country_id' => $data['country_id'],
                'tax_category_id' => $data['tax_category_id'],
                'quantity' => $data['quantity'],
                'value_org_currency' => $data['value'],
                'weight' => $data['weight'],
                'supplier_deliveries_count' => count($data['stock_deliveries']),
                'parts_count' => count($data['parts']),
                'invoices_count' => 0,
                'supplier_tax_numbers' => null,
                'valid_tax_numbers_count' => 0,
                'invalid_tax_numbers_count' => 0,
                'mode_of_transport' => IntrastatTransportModeEnum::ROAD,
                'delivery_terms' => IntrastatDeliveryTermsEnum::DAP,
                'nature_of_transaction' => IntrastatNatureOfTransactionEnum::OUTRIGHT_PURCHASE,
            ]);
        }
    }
}
