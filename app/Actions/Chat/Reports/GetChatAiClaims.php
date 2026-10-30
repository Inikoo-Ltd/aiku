<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Damaged, missing and wrong item claims the assistant drafted for, as chat:measure-claims
 * labelled them. One case per conversation and order: a chat gets several drafts on the same
 * claim, and the case takes the one closest to what staff did, so a claim counts once and the
 * AI is credited when any of its drafts proposed what was done. Without an order found there
 * is nothing to compare, so those stay out.
 */
class GetChatAiClaims
{
    use AsAction;

    public function cases(Group $group, int $days): Builder
    {
        $drafts = DB::table('chat_ai_drafts')
            ->where('group_id', $group->id)
            ->whereRaw("facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
            ->whereNotNull('claim_check')
            ->whereRaw("claim_check->>'label' <> 'no_order'")
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("distinct on (coalesce(chat_session_id, -meta_chat_session_id), claim_check->>'order_id') shop_id")
            ->selectRaw("(claim_check->>'customer_id')::int as customer_id, claim_check->>'label' as label, claim_check->>'band' as band, (claim_check->>'value')::numeric as value")
            ->selectRaw("claim_check->'jev'->>'verdict' as verdict")
            ->orderByRaw("coalesce(chat_session_id, -meta_chat_session_id), claim_check->>'order_id', array_position(array['as_proposed', 'differently', 'open', 'declined'], claim_check->>'label'), id desc");

        return DB::query()->fromSub($drafts, 'cases');
    }

    /**
     * One row per shop and value band.
     */
    public function byShopAndBand(Group $group, int $days): Builder
    {
        $byBand = DB::query()->fromSub($this->cases($group, $days), 'cases')
            ->join('shops', 'shops.id', 'cases.shop_id')
            ->selectRaw('shops.name as shop_name, cases.band')
            ->selectRaw(self::counts())
            ->groupByRaw('1, 2');

        return DB::query()->fromSub($byBand, 'claims')->select('claims.*')->selectRaw(self::accepted().' as accepted');
    }

    /**
     * The whole group by value band, for the cards above the tables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byBand(Group $group, int $days): array
    {
        return DB::query()->fromSub($this->cases($group, $days), 'cases')
            ->selectRaw('band')
            ->selectRaw(self::counts())
            ->groupBy('band')
            ->get()
            ->sortBy(fn ($row) => $row->band === 'unknown' ? PHP_INT_MAX : (int) $row->band)
            ->map(fn ($row) => [
                'band'        => $row->band,
                'cases'       => (int) $row->cases,
                'as_proposed' => (int) $row->as_proposed,
                'differently' => (int) $row->differently,
                'declined'    => (int) $row->declined,
                'open'        => (int) $row->open,
                'jev_agreed'  => (int) $row->jev_agreed,
                'jev_read'    => (int) $row->jev_read,
            ])
            ->values()
            ->all();
    }

    /**
     * Customers with more than one claim in the period, to set a per-customer limit.
     */
    public function repeatClaimers(Group $group, int $days): Builder
    {
        $claimers = DB::query()->fromSub($this->cases($group, $days), 'cases')
            ->join('customers', 'customers.id', 'cases.customer_id')
            ->join('shops', 'shops.id', 'cases.shop_id')
            ->selectRaw('customers.id as customer_id, customers.slug as customer_slug, customers.name as customer_name, shops.name as shop_name')
            ->selectRaw("count(*) as cases, count(*) filter (where label in ('as_proposed', 'differently')) as settled, coalesce(sum(value) filter (where label in ('as_proposed', 'differently')), 0) as settled_value")
            ->groupByRaw('1, 2, 3, 4')
            ->havingRaw('count(*) > 1');

        return DB::query()->fromSub($claimers, 'claimers')->select('claimers.*');
    }

    private static function counts(): string
    {
        return "count(*) as cases, count(*) filter (where label = 'as_proposed') as as_proposed, count(*) filter (where label = 'differently') as differently,"
            ." count(*) filter (where label = 'declined') as declined, count(*) filter (where label = 'open') as open,"
            ." count(*) filter (where verdict = 'as_proposed') as jev_agreed, count(*) filter (where verdict is not null and verdict <> 'not_yet') as jev_read";
    }

    private static function accepted(): string
    {
        return 'round(as_proposed::numeric / nullif(cases - open, 0), 3)';
    }
}
