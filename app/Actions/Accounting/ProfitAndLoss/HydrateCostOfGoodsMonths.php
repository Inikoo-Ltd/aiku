<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 01:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\ProfitAndLoss;

use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores each closed month's cost of goods per organisation, so the profit and loss reads 23 of its 24 months from a
 * few rows instead of summing millions of stock movements. Every month in the range is rewritten, months without
 * picks as 0, so a repair that changes old movements is picked up the next night (or straight away by running the
 * command). The current month is never stored: the profit and loss reads it live.
 */
class HydrateCostOfGoodsMonths
{
    use AsAction;

    public string $commandSignature = 'accounting:hydrate-cost-of-goods-months {--months=25 : closed months to rewrite, counting back from last month} {organisations?* : organisation slugs, all when none}';

    public function handle(Organisation $organisation, Carbon $from, Carbon $to): int
    {
        $from = $from->copy()->startOfMonth();
        $to   = $to->copy()->endOfMonth();
        if ($to->gte(now()->startOfMonth())) {
            $to = now()->startOfMonth()->subMonth()->endOfMonth();
        }
        if ($from->gt($to)) {
            return 0;
        }

        $amounts = GetProfitAndLoss::costOfGoodsQuery([$organisation->id], $from, $to)->get()->keyBy('month');
        $rows    = [];
        for ($month = $from->copy(); $month->lte($to); $month->addMonth()) {
            $key    = $month->format('Y-m');
            $rows[] = [
                'organisation_id'     => $organisation->id,
                'month'               => $key,
                'org_amount'          => $amounts->get($key)?->org_amount ?? 0,
                'grp_amount_external' => $amounts->get($key)?->grp_amount_external ?? 0,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        DB::table('cost_of_goods_months')->upsert($rows, ['organisation_id', 'month'], ['org_amount', 'grp_amount_external', 'updated_at']);

        return count($rows);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $months = max(1, (int) $command->option('months'));
        $to     = now()->startOfMonth()->subMonth();
        $from   = $to->copy()->subMonths($months - 1);
        $slugs  = $command->argument('organisations');

        Organisation::query()->when($slugs, fn ($query) => $query->whereIn('slug', $slugs))->orderBy('id')->get()
            ->each(function (Organisation $organisation) use ($command, $from, $to) {
                $started = microtime(true);
                $stored  = $this->handle($organisation, $from, $to);
                $command->info("$organisation->code: $stored months in ".round(microtime(true) - $started, 1).' s');
            });

        return 0;
    }
}
