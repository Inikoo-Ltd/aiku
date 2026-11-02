<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Models\Goods\StockFamily;
use App\Models\Goods\StockFamilyTimeSeriesRecord;
use App\Models\SysAdmin\Organisation;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetStockFamilyTimeSeriesOrganisations extends OrgAction
{
    use WithGoodsAuthorisation;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public function handle(StockFamily $stockFamily, StockFamilyTimeSeriesRecord $record): array
    {
        $currency = $stockFamily->group->currency->code;

        $from = $record->from?->copy()->startOfDay();
        $to   = $record->to?->copy()->endOfDay();

        $sales         = $this->salesByOrganisation($stockFamily->id, $from, $to);
        $salesLastYear = $this->salesByOrganisation($stockFamily->id, $from?->copy()->subYear(), $to?->copy()->subYear());

        $organisations = Organisation::whereIn('id', array_keys($sales))->get()->keyBy('id');

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
            ];
        }

        usort($rows, fn ($a, $b) => $b['sales_grp_currency_external'] <=> $a['sales_grp_currency_external']);

        return $rows;
    }

    protected function salesByOrganisation(int $stockFamilyId, ?Carbon $from, ?Carbon $to): array
    {
        if (!$from || !$to) {
            return [];
        }

        return DB::table('invoice_transaction_has_stocks as pivot')
            ->join('invoice_transactions', 'invoice_transactions.id', '=', 'pivot.invoice_transaction_id')
            ->where('pivot.stock_family_id', $stockFamilyId)
            ->where('invoice_transactions.date', '>=', $from)
            ->where('invoice_transactions.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at')
            ->where('invoice_transactions.is_partner', false)
            ->groupBy('invoice_transactions.organisation_id')
            ->select(['invoice_transactions.organisation_id', ...$this->pivotBasedSelects()])
            ->get()
            ->keyBy('organisation_id')
            ->all();
    }

    protected function calculateDelta(float $current, float $previous): ?array
    {
        if ($previous <= 0) {
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

    public function asController(StockFamily $stockFamily, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromGroup(group(), $request);

        $record = StockFamilyTimeSeriesRecord::find($request->query('record'));

        if (!$record || !$stockFamily->timeSeries()->whereKey($record->stock_family_time_series_id)->exists()) {
            return response()->json([
                'message' => __('Sales record not found'),
            ], 404);
        }

        return response()->json([
            'data' => $this->handle($stockFamily, $record),
        ]);
    }
}
