<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Actions\Chat\ChatSession\RecordAuraButtonClick;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Temporary (INI-524): clicks on the Aura box buttons in email chats over the same window as
 * Suggestions taken, per shop with its taken rate, and per person only on the shops the viewer
 * supervises. Goes with the aura_button_usage table.
 */
class GetAuraButtonUsage
{
    use AsAction;

    /**
     * @param  Collection<int, int>  $supervisedShopIds
     * @return array{days: int, buttons: list<string>, shops: list<array<string, mixed>>, users: list<array<string, mixed>>}
     */
    public function handle(Group $group, Collection $supervisedShopIds): array
    {
        $clicks = DB::table('aura_button_usage')
            ->join('shops', 'shops.id', 'aura_button_usage.shop_id')
            ->where('shops.group_id', $group->id)
            ->where('aura_button_usage.date', '>=', now()->subDays(GetChatAiAcceptance::RATE_DAYS)->toDateString());

        $taken = GetChatAiAcceptance::make()->byShop($group)->get()->keyBy('shop_id');

        $shops = (clone $clicks)
            ->select(['shops.id', 'shops.code', 'shops.name', 'aura_button_usage.button'])
            ->selectRaw('sum(aura_button_usage.clicks) as clicks, count(distinct aura_button_usage.user_id) as people')
            ->groupBy('shops.id', 'shops.code', 'shops.name', 'aura_button_usage.button')
            ->get()
            ->groupBy('id')
            ->map(fn (Collection $rows) => [
                'code'   => $rows->first()->code,
                'name'   => $rows->first()->name,
                'rate'   => isset($taken[$rows->first()->id]->rate) ? (int) $taken[$rows->first()->id]->rate : null,
                'clicks' => $rows->pluck('clicks', 'button')->map(fn ($count) => (int) $count)->all(),
                'people' => (int) $rows->max('people'),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $users = $supervisedShopIds->isEmpty() ? [] : (clone $clicks)
            ->join('users', 'users.id', 'aura_button_usage.user_id')
            ->whereIn('aura_button_usage.shop_id', $supervisedShopIds)
            ->select(['users.id', 'users.contact_name', 'aura_button_usage.button'])
            ->selectRaw("sum(aura_button_usage.clicks) as clicks, string_agg(distinct shops.code, ', ' order by shops.code) as shops")
            ->groupBy('users.id', 'users.contact_name', 'aura_button_usage.button')
            ->get()
            ->groupBy('id')
            ->map(fn (Collection $rows) => [
                'name'   => $rows->first()->contact_name,
                'shops'  => $rows->pluck('shops')->flatMap(fn ($codes) => explode(', ', $codes))->unique()->sort()->implode(', '),
                'clicks' => $rows->pluck('clicks', 'button')->map(fn ($count) => (int) $count)->all(),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'days'    => GetChatAiAcceptance::RATE_DAYS,
            'buttons' => RecordAuraButtonClick::BUTTONS,
            'shops'   => $shops,
            'users'   => $users,
        ];
    }
}
