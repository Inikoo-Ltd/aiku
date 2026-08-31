<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterProductCategory\Json;

use App\Actions\Masters\MasterProductCategory\WithMasterProductCategoryCustomerTotals;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryTimeSeriesRecord;
use App\Models\SysAdmin\Organisation;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetMasterProductCategoryTimeSeriesOrganisations extends OrgAction
{
    use WithMastersAuthorisation;
    use WithMasterProductCategoryCustomerTotals;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public function handle(MasterProductCategory $masterProductCategory, MasterProductCategoryTimeSeriesRecord $record): array
    {
        $column   = $this->masterProductCategoryTransactionColumn($masterProductCategory);
        $currency = $masterProductCategory->group->currency->code;

        $from = $record->from?->copy()->startOfDay();
        $to   = $record->to?->copy()->endOfDay();

        $sales         = $this->salesByOrganisation($column, $masterProductCategory->id, $from, $to);
        $salesLastYear = $this->salesByOrganisation(
            $column,
            $masterProductCategory->id,
            $from?->copy()->subYear(),
            $to?->copy()->subYear()
        );

        $firstPurchases = $this->getCustomerFirstPurchases($masterProductCategory);
        $organisations  = Organisation::whereIn('id', array_keys($sales))->get()->keyBy('id');

        $rows = [];

        foreach ($sales as $organisationId => $stats) {
            $salesGrpCurrency   = (float) $stats->sales_grp_currency_external;
            $salesGrpCurrencyLy = (float) ($salesLastYear[$organisationId]->sales_grp_currency_external ?? 0);

            $rows[] = [
                'organisation_id'                   => $organisationId,
                'name'                              => $organisations[$organisationId]?->name,
                'code'                              => $organisations[$organisationId]?->code,
                'currency_code'                     => $currency,
                'sales_grp_currency_external'       => $salesGrpCurrency,
                'sales_grp_currency_external_ly'    => $salesGrpCurrencyLy,
                'sales_grp_currency_external_delta' => $this->calculateDelta($salesGrpCurrency, $salesGrpCurrencyLy),
                'invoices'                          => (int) $stats->invoices,
                'refunds'                           => (int) $stats->refunds,
                'customers_invoiced'                => (int) $stats->customers_invoiced,
                'total_customers'                   => $this->getTotalCustomersUpTo($firstPurchases, $record->to?->toDateString(), $organisationId),
            ];
        }

        usort($rows, fn ($a, $b) => $b['sales_grp_currency_external'] <=> $a['sales_grp_currency_external']);

        return $rows;
    }

    protected function salesByOrganisation(string $column, int $masterProductCategoryId, ?Carbon $from, ?Carbon $to): array
    {
        if (!$from || !$to) {
            return [];
        }

        return DB::table('invoice_transactions')
            ->where($column, $masterProductCategoryId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at')
            ->groupBy('organisation_id')
            ->select(['organisation_id', ...$this->fullInvoiceTransactionSelects()])
            ->get()
            ->keyBy('organisation_id')
            ->all();
    }

    protected function calculateDelta(float $current, float $previous): ?array
    {
        if (!$previous) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value'       => $delta,
            'formatted'   => number_format($delta, 1).'%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromGroup(group(), $request);

        $record = MasterProductCategoryTimeSeriesRecord::find($request->query('record'));

        if (!$record || !$masterProductCategory->timeSeries()->whereKey($record->master_product_category_time_series_id)->exists()) {
            return response()->json([
                'message' => __('Sales record not found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->handle($masterProductCategory, $record),
        ]);
    }
}
