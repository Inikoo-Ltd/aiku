<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetChatScopeShops
{
    use AsObject;

    /**
     * The shops whose conversations a member of staff may read: the ones an agent is assigned
     * to, or the ones their own authorisation already covers when they are not an agent.
     */
    public function handle(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        $capabilities = GetChatCapabilities::run($user);

        if (!$capabilities['can_view']) {
            return collect();
        }

        return $capabilities['is_agent']
            ? $this->agentShops($user)
            : $this->authorisedShops($user);
    }

    /** @return array<int, int> */
    public function shopIds(?User $user): array
    {
        return $this->handle($user)->pluck('id')->all();
    }

    private function agentShops(User $user): Collection
    {
        $assignments = $user->chatAgent->shopAssignments()->get();

        $orgWideOrganisationIds = $assignments->whereNull('shop_id')->pluck('organisation_id')->unique();
        $shopIds                = $assignments->pluck('shop_id')->filter()->unique();

        return $this->query($shopIds, $orgWideOrganisationIds);
    }

    private function authorisedShops(User $user): Collection
    {
        $shopIds = $user->authorisedShops()->pluck('shops.id');

        $organisationIds = $user->authorisedOrganisations()->pluck('organisations.id')
            ->filter(fn ($organisationId) => $user->authTo(['org-supervisor.'.$organisationId, 'shops-view.'.$organisationId]));

        return $this->query($shopIds, $organisationIds);
    }

    private function query(Collection $shopIds, Collection $organisationIds): Collection
    {
        if ($shopIds->isEmpty() && $organisationIds->isEmpty()) {
            return collect();
        }

        return Shop::with('organisation')
            ->where('state', ShopStateEnum::OPEN)
            ->where(function ($query) use ($shopIds, $organisationIds) {
                $query->whereIn('id', $shopIds);

                if ($organisationIds->isNotEmpty()) {
                    $query->orWhereIn('organisation_id', $organisationIds);
                }
            })
            ->get();
    }
}
