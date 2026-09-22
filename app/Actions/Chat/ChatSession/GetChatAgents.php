<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatAgents
{
    use AsAction;

    /**
     * Who a conversation can be handed to: the shops somebody works come from the chat
     * permission their customer service position grants, not from the shop assignment
     * table that used to carry it. Managers are left out on purpose, since a chat is
     * handed to somebody who answers them rather than to somebody who oversees them.
     */
    public function handle(): array
    {
        $agents = ChatAgent::with(['user', 'activePhoneCall'])->get()
            ->filter(fn (ChatAgent $agent) => $agent->user && $agent->user->status);

        $shopsByUser = $this->shopsByUser($agents->pluck('user_id')->all());
        $shopNames   = $this->shopNames($shopsByUser->flatten()->unique()->all());

        return $agents->map(function (ChatAgent $agent) use ($shopsByUser, $shopNames) {
            $shopIds = $shopsByUser->get($agent->user_id, collect());

            if ($shopIds->isEmpty() || !$agent->isAvailableForChat()) {
                return null;
            }

            $name = $agent->user->contact_name ?? $agent->user->username ?? 'Unknown';

            return [
                'label'        => $name,
                'name'         => $name,
                'agent_id'     => $agent->id,
                'image'        => $agent->user->imageSources(48, 48),
                'shop_names'   => $shopIds->map(fn ($id) => $shopNames[$id] ?? '-')->sort()->implode(', '),
                'is_available' => true,
            ];
        })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, int>> shop ids keyed by user id
     */
    private function shopsByUser(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $granted = DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', 'User')
            ->whereIn('model_has_roles.model_id', $userIds)
            ->where(function ($query) {
                $query->where('permissions.name', 'like', 'chat.%')
                    ->orWhere('permissions.name', 'like', 'fulfilment-chat.%');
            })
            ->select('model_has_roles.model_id as user_id', 'permissions.name')
            ->distinct()
            ->get();

        // A fulfilment shop grants chat by its fulfilment rather than by itself.
        $shopByFulfilment = Fulfilment::pluck('shop_id', 'id');

        return $granted->groupBy('user_id')->map(
            fn ($rows) => $rows->map(function ($row) use ($shopByFulfilment) {
                if (preg_match('/^chat\.(\d+)$/', $row->name, $match)) {
                    return (int) $match[1];
                }

                if (preg_match('/^fulfilment-chat\.(\d+)$/', $row->name, $match)) {
                    return $shopByFulfilment[(int) $match[1]] ?? null;
                }

                return null;
            })->filter()->unique()->values()
        );
    }

    /**
     * @param  array<int, int>  $shopIds
     * @return array<int, string>
     */
    private function shopNames(array $shopIds): array
    {
        if ($shopIds === []) {
            return [];
        }

        return Shop::with('organisation')->findMany($shopIds)
            ->mapWithKeys(fn (Shop $shop) => [
                $shop->id => ($shop->organisation->code ?? '-').' | '.$shop->name,
            ])
            ->all();
    }

    public function asController(): JsonResponse
    {
        $agents = $this->handle();

        return response()->json([
            'success' => true,
            'message' => 'Chat agents retrieved successfully',
            'data'    => $agents

        ]);
    }
}
