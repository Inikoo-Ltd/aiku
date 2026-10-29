<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The confidence map: every kind of chat (shop, intent and risk) the assistant drafted for, with
 * what staff did with those drafts and how customers took the replies. A cell earns trust only
 * on its lower bound, the worst rate its numbers still allow, so twenty lucky drafts never look
 * like proof. Intent and risk are the routine's own tags, or Jev's where the routine gave none.
 */
class GetChatAiConfidenceMap
{
    use AsAction;

    public const array DAYS = [7, 30, 90];

    public const string UNTAGGED = 'untagged';

    /**
     * One row per cell, for a table: counts, shares are left to the reader.
     */
    public function cells(Group $group, int $days): Builder
    {
        $alone   = "change_class in ('as_is', 'cosmetic')";
        $harmed  = "(flagged_wrong_at is not null or coalesce(customer_reaction, '') in ('confused', 'complaint'))";
        $cells   = DB::table('chat_ai_drafts')
            ->join('shops', 'shops.id', 'chat_ai_drafts.shop_id')
            ->where('chat_ai_drafts.group_id', $group->id)
            ->whereRaw("chat_ai_drafts.facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
            ->whereNotNull('chat_ai_drafts.change_class')
            ->where('chat_ai_drafts.created_at', '>=', now()->subDays($days))
            ->selectRaw('chat_ai_drafts.shop_id, shops.slug as shop_slug, shops.name as shop_name')
            ->selectRaw("coalesce(chat_ai_drafts.intent, chat_ai_drafts.jev_check->>'intent', ?) as intent", [self::UNTAGGED])
            ->selectRaw("coalesce(chat_ai_drafts.risk, chat_ai_drafts.jev_check->>'risk', ?) as risk", [self::UNTAGGED])
            ->selectRaw('count(*) as decided')
            ->selectRaw("count(*) filter (where $alone) as alone")
            ->selectRaw("count(*) filter (where $alone and not $harmed) as alone_clean")
            ->selectRaw("count(*) filter (where change_class = 'gaps_filled') as gaps_filled")
            ->selectRaw("count(*) filter (where change_class in ('small', 'big', 'rewritten')) as changed")
            ->selectRaw("count(*) filter (where change_class in ('own_reply', 'discarded')) as not_used")
            ->selectRaw("count(*) filter (where (facts->'near_match'->>'score')::int >= 2) as close")
            ->selectRaw("count(*) filter (where taken_at is not null and change_class in ('big', 'rewritten', 'own_reply')) as use_rewrote")
            ->selectRaw('count(*) filter (where copied_at is not null) as copied')
            ->selectRaw("count(*) filter (where $harmed) as harms")
            ->selectRaw('count(*) filter (where would_send) as would_send')
            ->selectRaw("count(*) filter (where would_send and $alone) as would_send_right")
            ->selectRaw("count(*) filter (where intent is not null and jev_check->>'intent' is not null and intent <> jev_check->>'intent') as intent_disagree")
            ->selectRaw('round(avg(lag_seconds) / 60) as lag_minutes')
            ->groupByRaw('1, 2, 3, 4, 5');

        return DB::query()->fromSub($cells, 'cells')->select('cells.*')->selectRaw(self::lowerBound('alone_clean', 'decided').' as lower_bound');
    }

    /**
     * For the dashboard: the whole map in a few numbers and the cells closest to earning it.
     *
     * @return array<string, mixed>
     */
    public function handle(Group $group, int $days = 30): array
    {
        $cells  = collect($this->cells($group, $days)->get());
        $sum    = fn (string $column) => (int) $cells->sum($column);
        $decided = $sum('decided');

        return [
            'days'             => $days,
            'cells'            => $cells->count(),
            'decided'          => $decided,
            'alone'            => $sum('alone'),
            'alone_clean'      => $sum('alone_clean'),
            'gaps_filled'      => $sum('gaps_filled'),
            'harms'            => $sum('harms'),
            'would_send'       => $sum('would_send'),
            'would_send_right' => $sum('would_send_right'),
            'untagged'         => (int) $cells->where('intent', self::UNTAGGED)->sum('decided'),
            'lower_bound'      => $decided ? round(self::wilson($sum('alone_clean'), $decided), 3) : null,
            'best'             => $cells->sortByDesc('lower_bound')->take(5)->map(fn ($cell) => [
                'shop'        => $cell->shop_name,
                'intent'      => $cell->intent,
                'risk'        => $cell->risk,
                'decided'     => (int) $cell->decided,
                'alone_clean' => (int) $cell->alone_clean,
                'lower_bound' => (float) $cell->lower_bound,
            ])->values()->all(),
        ];
    }

    /**
     * Wilson score lower bound at 95%: the lowest share of successes the numbers still allow.
     */
    public static function wilson(int $successes, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        $share = $successes / $total;
        $z     = 1.96;

        return ($share + $z * $z / (2 * $total) - $z * sqrt($share * (1 - $share) / $total + $z * $z / (4 * $total * $total))) / (1 + $z * $z / $total);
    }

    private static function lowerBound(string $successes, string $total): string
    {
        $share = "($successes::numeric / $total)";

        return "round(($share + 1.9208 / $total - 1.96 * sqrt($share * (1 - $share) / $total + 0.9604 / ($total::numeric * $total))) / (1 + 3.8416 / $total), 3)";
    }
}
