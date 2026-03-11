<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 11 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Enums\Catalogue\Asset\AssetHealthRankEnum;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateHealthRank implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:asset-health-rank';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Asset health ranks updated.');
    }

    public function handle(): void
    {
        $twelveMonthsAgo = Carbon::now()->subYear();

        $stats = DB::table('invoice_transactions')
            ->where('in_process', false)
            ->whereNull('deleted_at')
            ->whereNotNull('asset_id')
            ->selectRaw('
                asset_id,
                MAX(date) as last_sale_date,
                SUM(CASE WHEN date >= ? THEN quantity ELSE 0 END) as qty_12m,
                SUM(CASE WHEN date >= ? THEN COALESCE(grp_net_amount, 0) ELSE 0 END) as revenue_12m
            ', [$twelveMonthsAgo, $twelveMonthsAgo])
            ->groupBy('asset_id')
            ->get()
            ->keyBy('asset_id');

        $cutoff = Carbon::now()->subDays(365);

        $inactiveIds = $stats
            ->filter(fn ($row) => is_null($row->last_sale_date) || Carbon::parse($row->last_sale_date)->lt($cutoff))
            ->keys()
            ->all();

        $activeStats = $stats
            ->filter(fn ($row) => !is_null($row->last_sale_date) && Carbon::parse($row->last_sale_date)->gte($cutoff))
            ->values();

        if ($activeStats->isEmpty()) {
            if (!empty($inactiveIds)) {
                DB::table('assets')->whereIn('id', $inactiveIds)->update(['health_rank' => AssetHealthRankEnum::D->value]);
            }
            return;
        }

        $maxQty = $activeStats->max('qty_12m') ?: 1;
        $maxRevenue = $activeStats->max('revenue_12m') ?: 1;

        $scored = $activeStats->map(function ($row) use ($maxQty, $maxRevenue) {
            $normQty     = $maxQty > 0 ? ($row->qty_12m / $maxQty) : 0;
            $normRevenue = $maxRevenue > 0 ? ($row->revenue_12m / $maxRevenue) : 0;

            return [
                'asset_id' => $row->asset_id,
                'score'    => ($normQty + $normRevenue) / 2,
            ];
        })->sortBy('score')->values();

        $count        = $scored->count();
        $p50Index     = (int) floor($count * 0.50);
        $p85Index     = (int) floor($count * 0.85);

        $rankA = $scored->slice($p85Index)->pluck('asset_id')->all();
        $rankB = $scored->slice($p50Index, $p85Index - $p50Index)->pluck('asset_id')->all();
        $rankC = $scored->slice(0, $p50Index)->pluck('asset_id')->all();

        if (!empty($inactiveIds)) {
            DB::table('assets')->whereIn('id', $inactiveIds)->update(['health_rank' => AssetHealthRankEnum::D->value]);
        }
        if (!empty($rankA)) {
            DB::table('assets')->whereIn('id', $rankA)->update(['health_rank' => AssetHealthRankEnum::A->value]);
        }
        if (!empty($rankB)) {
            DB::table('assets')->whereIn('id', $rankB)->update(['health_rank' => AssetHealthRankEnum::B->value]);
        }
        if (!empty($rankC)) {
            DB::table('assets')->whereIn('id', $rankC)->update(['health_rank' => AssetHealthRankEnum::C->value]);
        }

        $assetIdsWithData = $stats->keys()->all();
        DB::table('assets')
            ->whereNotIn('id', $assetIdsWithData)
            ->whereNotNull('id')
            ->update(['health_rank' => AssetHealthRankEnum::D->value]);
    }
}
