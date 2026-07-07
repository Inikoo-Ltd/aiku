<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent;

use App\Actions\OrgAction;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ShopHasChatAgent;

class AssignChatAgentToScope extends OrgAction
{
    public function handle(array $data, ChatAgent $agent): void
    {
        $organisationId = $data['organisation_id'];

        $shopIds = collect($data['shop_id'] ?? [null])
            ->map(fn ($id) => $id === null ? null : (int) $id)
            ->unique()
            ->values();

        foreach ($shopIds as $shopId) {
            $record = ShopHasChatAgent::withTrashed()->firstOrNew([
                'organisation_id' => $organisationId,
                'shop_id'         => $shopId,
                'chat_agent_id'   => $agent->id,
            ]);

            if ($record->exists) {
                if ($record->trashed()) {
                    $record->restore();
                }

                continue;
            }

            $record->save();
        }
    }


    public function asController(array $data, ChatAgent $agent): null
    {
        return $this->handle($data, $agent);
    }

    public function update(array $data, ChatAgent $agent): void
    {
        $organisationId = $data['organisation_id'];

        // Normalize incoming shop ids
        $newShopIds = collect($data['shop_id'] ?? [null])
            ->map(function ($id) {
                return $id === null ? null : (int) $id;
            })
            ->unique()
            ->values()
            ->all();

        // Existing assignments
        $existingShopIds = ShopHasChatAgent::query()
            ->where('organisation_id', $organisationId)
            ->where('chat_agent_id', $agent->id)
            ->pluck('shop_id')
            ->map(function ($id) {
                return $id === null ? null : (int) $id;
            })
            ->values()
            ->all();

        // Delete removed shops
        $toDelete = array_diff($existingShopIds, $newShopIds);

        if (!empty($toDelete)) {
            ShopHasChatAgent::query()
                ->where('organisation_id', $organisationId)
                ->where('chat_agent_id', $agent->id)
                ->whereIn('shop_id', $toDelete)
                ->delete();
        }

        // Insert new shops
        $toInsert = array_diff($newShopIds, $existingShopIds);

        foreach ($toInsert as $shopId) {
            $record = ShopHasChatAgent::withTrashed()->firstOrNew([
                'organisation_id' => $organisationId,
                'shop_id'         => $shopId,
                'chat_agent_id'   => $agent->id,
            ]);

            if ($record->exists && $record->trashed()) {
                $record->restore();
            } elseif (! $record->exists) {
                $record->save();
            }
        }
    }
}
