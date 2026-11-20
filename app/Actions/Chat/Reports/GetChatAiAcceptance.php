<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How much of the assistant's reply suggestions staff take, per shop and per person, over the
 * last four weeks and week by week. Only drafts with an outcome count (change_class set): one
 * still waiting, or replaced by a newer draft, says nothing yet. Taken means sent as it was, with
 * gaps filled or edited; rewritten, ignored for an own reply, or discarded is not taken.
 */
class GetChatAiAcceptance
{
    use AsAction;

    public const array ADOPTED = ['as_is', 'cosmetic', 'gaps_filled', 'small', 'big'];

    public const int HIGH = 70;

    public const int MEDIUM = 40;

    public const int WEEKS = 8;

    public const int MIN_DRAFTS_FOR_BAND = 20;

    public const int MIN_DRAFTS_FOR_WEEK = 5;

    public const int RATE_DAYS = 28;

    public const int ANGRY_DAYS = 7;

    public const int ANGRY_AFTER = 2;

    public static function band(int $adopted, int $decided, int $minimum = self::MIN_DRAFTS_FOR_BAND): ?string
    {
        if ($decided < $minimum) {
            return null;
        }

        $rate = 100 * $adopted / $decided;

        return match (true) {
            $rate >= self::HIGH   => 'high',
            $rate >= self::MEDIUM => 'medium',
            default               => 'low',
        };
    }

    /**
     * Monday of each of the last WEEKS weeks, this week first.
     *
     * @return array<int, Carbon>
     */
    public static function weekStarts(?Carbon $now = null): array
    {
        $monday = ($now ?? now())->copy()->startOfWeek();

        return array_map(fn (int $weeksAgo) => $monday->copy()->subWeeks($weeksAgo), range(0, self::WEEKS - 1));
    }

    public function decided(Group $group): Builder
    {
        return DB::table('chat_ai_drafts')
            ->where('chat_ai_drafts.group_id', $group->id)
            ->whereRaw("chat_ai_drafts.facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
            ->whereNotNull('chat_ai_drafts.change_class')
            ->where('chat_ai_drafts.created_at', '>=', self::weekStarts()[self::WEEKS - 1]);
    }

    private function counts(Builder $query): Builder
    {
        $adopted = "chat_ai_drafts.change_class in ('".implode("', '", self::ADOPTED)."')";
        $recent  = now()->subDays(self::RATE_DAYS)->toDateTimeString();

        $query->selectRaw('count(*) filter (where chat_ai_drafts.created_at >= ?) as decided', [$recent])
            ->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and $adopted) as adopted", [$recent])
            ->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.change_class = 'rewritten') as rewritten", [$recent])
            ->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.change_class = 'own_reply') as ignored", [$recent])
            ->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.change_class = 'discarded') as discarded", [$recent])
            ->selectRaw('count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.time_lost) as time_lost', [$recent])
            ->selectRaw('coalesce(sum(chat_ai_drafts.time_lost_seconds) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.time_lost), 0) as time_lost_seconds', [$recent])
            ->selectRaw("round(100.0 * count(*) filter (where chat_ai_drafts.created_at >= ? and $adopted) / nullif(count(*) filter (where chat_ai_drafts.created_at >= ?), 0)) as rate", [$recent, $recent]);

        foreach (self::weekStarts() as $weeksAgo => $start) {
            $from = $start->toDateTimeString();
            $to   = $start->copy()->addWeek()->toDateTimeString();
            $query->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.created_at < ?) as d$weeksAgo", [$from, $to])
                ->selectRaw("count(*) filter (where chat_ai_drafts.created_at >= ? and chat_ai_drafts.created_at < ? and $adopted) as a$weeksAgo", [$from, $to]);
        }

        return $query;
    }

    /**
     * Shop ids whose last RATE_DAYS put them in the band.
     *
     * @return array<int, int>
     */
    public function shopIdsInBand(Group $group, string $band): array
    {
        return collect($this->byShop($group)->get())
            ->filter(fn ($shop) => (self::band((int) $shop->adopted, (int) $shop->decided) ?? 'few') === $band)
            ->pluck('shop_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function byShop(Group $group): Builder
    {
        $rows = $this->counts(
            $this->decided($group)
                ->join('shops', 'shops.id', 'chat_ai_drafts.shop_id')
                ->select(['shops.id as shop_id', 'shops.slug as shop_slug', 'shops.code as shop_code', 'shops.name as shop_name', 'shops.type as shop_type'])
                ->groupBy('shops.id', 'shops.slug', 'shops.code', 'shops.name', 'shops.type')
        );

        return DB::query()->fromSub($rows, 'shops_acceptance')->select('shops_acceptance.*');
    }

    /**
     * One row per person who decided a draft on the given shops: the one who used, edited or
     * discarded it, or answered beside it (by the agent who sent the reply when the draft does
     * not record who decided it).
     *
     * @param  Collection<int, int>  $shopIds
     */
    public function byUser(Group $group, Collection $shopIds): Builder
    {
        $rows = $this->counts(
            $this->decided($group)
                ->leftJoin('chat_messages as reply', fn ($join) => $join->on('reply.id', '=', 'chat_ai_drafts.reply_message_id')->whereNotNull('chat_ai_drafts.chat_session_id')->where('reply.sender_type', 'agent'))
                ->leftJoin('chat_agents', 'chat_agents.id', '=', 'reply.sender_id')
                ->join('users', 'users.id', '=', DB::raw('coalesce(chat_ai_drafts.decided_by_user_id, chat_agents.user_id)'))
                ->join('shops', 'shops.id', 'chat_ai_drafts.shop_id')
                ->whereIn('chat_ai_drafts.shop_id', $shopIds)
                ->select(['users.id as user_id', 'users.contact_name as user_name'])
                ->selectRaw("string_agg(distinct shops.code, ', ' order by shops.code) as shops")
                ->groupBy('users.id', 'users.contact_name')
        );

        return DB::query()->fromSub($rows, 'users_acceptance')->select('users_acceptance.*');
    }

    /**
     * Aura turns red for an agent who, in the last ANGRY_DAYS, wrote again by hand at least
     * ANGRY_AFTER suggestions she had right (time_lost). Only the mood reaches the agent, never
     * the count.
     */
    public function isAngryWith(int $userId): bool
    {
        return DB::table('chat_ai_drafts')
            ->leftJoin('chat_messages as reply', fn ($join) => $join->on('reply.id', '=', 'chat_ai_drafts.reply_message_id')->whereNotNull('chat_ai_drafts.chat_session_id')->where('reply.sender_type', 'agent'))
            ->leftJoin('chat_agents', 'chat_agents.id', '=', 'reply.sender_id')
            ->where('chat_ai_drafts.time_lost', true)
            ->where('chat_ai_drafts.decided_at', '>=', now()->subDays(self::ANGRY_DAYS))
            ->whereRaw('coalesce(chat_ai_drafts.decided_by_user_id, chat_agents.user_id) = ?', [$userId])
            ->count() >= self::ANGRY_AFTER;
    }

    /**
     * The band of each shop and the group's rate this week against last week, for the head of
     * the page.
     *
     * @return array<string, mixed>
     */
    public function handle(Group $group): array
    {
        $shops = collect($this->byShop($group)->get());
        $sum   = fn (string $column) => (int) $shops->sum($column);
        $bands = ['high' => [], 'medium' => [], 'low' => [], 'few' => []];

        foreach ($shops->sortByDesc('rate') as $shop) {
            $bands[self::band((int) $shop->adopted, (int) $shop->decided) ?? 'few'][] = [
                'code'    => $shop->shop_code,
                'url'     => route('grp.chat.ai.aiku_drafts', ['filter' => ['shop' => $shop->shop_slug, 'kind' => 'draft']]),
                'rate'    => $shop->rate === null ? null : (int) $shop->rate,
                'decided' => (int) $shop->decided,
            ];
        }

        $weeks = [];
        foreach (self::weekStarts() as $weeksAgo => $start) {
            $weeks[] = [
                'start'   => $start->toDateString(),
                'decided' => $sum("d$weeksAgo"),
                'adopted' => $sum("a$weeksAgo"),
            ];
        }

        return [
            'target'  => self::HIGH,
            'medium'  => self::MEDIUM,
            'days'    => self::RATE_DAYS,
            'decided' => $sum('decided'),
            'adopted' => $sum('adopted'),
            'bands'   => $bands,
            'weeks'   => array_reverse($weeks),
        ];
    }
}
