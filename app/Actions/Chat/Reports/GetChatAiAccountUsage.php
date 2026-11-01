<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Models\SysAdmin\Group;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The latest weekly usage reading of each claude.ai account from the last two weeks, with a
 * straight-line forecast of how long before its reset it runs out.
 */
class GetChatAiAccountUsage
{
    use AsAction;

    /**
     * @return Collection<string, array{percent_used: float, week_gone_percent: float, resets_at: string, sampled_at: string, runs_out_early_seconds: ?int}>
     */
    public function handle(Group $group): Collection
    {
        return DB::table('chat_ai_account_usage_samples')
            ->where('group_id', $group->id)
            ->where('sampled_at', '>=', now()->subDays(14))
            ->selectRaw('distinct on (account) account, percent_used, resets_at, sampled_at')
            ->orderBy('account')
            ->orderByDesc('sampled_at')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->account => self::weeklyUsage((float) $row->percent_used, Carbon::parse($row->resets_at), Carbon::parse($row->sampled_at))]);
    }

    /**
     * At the rate used so far this week, how long before the reset the account runs out (null when
     * it lasts, or the reading is from a week already reset).
     *
     * @return array{percent_used: float, week_gone_percent: float, resets_at: string, sampled_at: string, runs_out_early_seconds: ?int}
     */
    public static function weeklyUsage(float $percentUsed, Carbon $resetsAt, Carbon $sampledAt): array
    {
        $week    = 7 * 86400;
        $left    = $sampledAt->diffInSeconds($resetsAt, false);
        $elapsed = $week - $left;

        $early = null;
        if ($left > 0 && $elapsed > 0 && $percentUsed > 0) {
            $runsOutIn = $percentUsed >= 100 ? 0 : $elapsed * (100 - $percentUsed) / $percentUsed;
            $early     = $runsOutIn < $left ? (int) round($left - $runsOutIn) : null;
        }

        return [
            'percent_used'           => $percentUsed,
            'week_gone_percent'      => round(max(0, min($week, $elapsed)) / $week * 100, 1),
            'resets_at'              => $resetsAt->toIso8601String(),
            'sampled_at'             => $sampledAt->toIso8601String(),
            'runs_out_early_seconds' => $early,
        ];
    }
}
