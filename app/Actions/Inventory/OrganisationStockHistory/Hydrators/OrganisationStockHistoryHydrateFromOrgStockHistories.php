<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory\Hydrators;

use App\Actions\Traits\WithStockHistoryArchiveRead;
use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A day beyond the retention window has its per SKU rows in the archive, so the sums are taken
 * from wherever that day actually lives. The organisation row being written is always local:
 * organisation_stock_histories is never archived.
 */
class OrganisationStockHistoryHydrateFromOrgStockHistories implements ShouldBeUnique
{
    use AsAction;
    use WithStockHistoryArchiveRead;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $organisationStockHistory): int
    {
        return $organisationStockHistory ?? 0;
    }

    public function getCommandSignature(): string
    {
        return 'hydrate:organisation_stock_histories {organisation?}';
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $query = DB::table('organisation_stock_histories');
        if ($command->argument('organisation')) {
            $organisation = \App\Models\SysAdmin\Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
            $query->where('organisation_id', $organisation->id);
        }
        $ids = $query->orderBy('date')->pluck('id');

        $progressBar = $command->getOutput()->createProgressBar(count($ids));
        $progressBar->start();
        foreach ($ids as $id) {
            $this->handle($id);
            $progressBar->advance();
        }
        $progressBar->finish();
        $command->newLine();

        return 0;
    }

    public function handle(?int $organisationStockHistoryId): void
    {
        if (!$organisationStockHistoryId) {
            return;
        }
        $organisationStockHistory = OrganisationStockHistory::find($organisationStockHistoryId);
        if (!$organisationStockHistory) {
            return;
        }

        $connection = $this->stockHistoryDayConnection($organisationStockHistory) ?? 'aiku_no_sticky';

        $stockData = DB::connection($connection)->table('org_stock_histories')
            ->selectRaw('sum(non_moving_1y*lpp_per_sku) as value_dormant_stock_1y')
            ->selectRaw('sum(non_moving_1y*wac_per_sku) as value_dormant_stock_1y_wac')
            ->selectRaw('sum(non_moving_1y*fifo_per_sku) as value_dormant_stock_1y_fifo')
            ->selectRaw('sum(org_stock_lpp_value) as org_stock_lpp_values')
            ->selectRaw('sum(grp_stock_lpp_value) as grp_stock_lpp_values')
            ->selectRaw('sum(org_stock_wac_value) as org_stock_wac_values')
            ->selectRaw('sum(grp_stock_wac_value) as grp_stock_wac_values')
            ->selectRaw('sum(org_stock_fifo_value) as org_stock_fifo_values')
            ->selectRaw('sum(grp_stock_fifo_value) as grp_stock_fifo_values')
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->first();

        $stockNotSold = DB::connection($connection)->table('org_stock_histories')
            ->where('org_stock_histories.sold_within_1y', false)
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->count();

        $stockLocationData = DB::connection($connection)->table('location_org_stock_histories')
            ->selectRaw('COUNT(DISTINCT location_id) as number_locations')
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->first();

        $percentageValueDormantStock1y = 0;
        if ($stockData->org_stock_lpp_values > 0) {
            $percentageValueDormantStock1y = round(($stockData->value_dormant_stock_1y ?? 0) / $stockData->org_stock_lpp_values * 100, 2);
        }

        $organisationStockHistory->update([
            'org_stock_lpp_value'                   => $stockData->org_stock_lpp_values ?? 0,
            'grp_stock_lpp_value'                   => $stockData->grp_stock_lpp_values ?? 0,
            'org_stock_wac_value'               => $stockData->org_stock_wac_values,
            'grp_stock_wac_value'               => $stockData->grp_stock_wac_values,
            'org_stock_fifo_value'              => $stockData->org_stock_fifo_values,
            'grp_stock_fifo_value'              => $stockData->grp_stock_fifo_values,
            'number_locations'                  => $stockLocationData->number_locations ?? 0,
            'number_org_stocks_not_sold_1y'     => $stockNotSold,
            'percentage_value_dormant_stock_1y' => $percentageValueDormantStock1y,
            'value_dormant_stock_1y'            => $stockData->value_dormant_stock_1y ?? 0,
            'value_dormant_stock_1y_wac'        => $stockData->value_dormant_stock_1y_wac,
            'value_dormant_stock_1y_fifo'       => $stockData->value_dormant_stock_1y_fifo,
        ]);

        OrganisationStockHistoryHydrateOutOfStock::run($organisationStockHistory->id);

    }


}
