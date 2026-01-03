<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ShopHasChatAgent;
use Illuminate\Validation\ValidationException;

class AssignChatAgentToScope extends OrgAction
{
    public function handle(array $data, ChatAgent $agent)
    {

        $organisationId = $data['organisation_id'];
        $shopIds        = $data['shop_id'] ?? [null];

        $shopIds = is_array($shopIds) ? $shopIds : [$shopIds];

        foreach ($shopIds as $shopId) {

            $exists = ShopHasChatAgent::query()
                ->where('organisation_id', $organisationId)
                ->where('chat_agent_id', $agent->id)
                ->when(
                    $shopId,
                    fn ($q) => $q->where('shop_id', $shopId),
                    fn ($q) => $q->whereNull('shop_id')
                )
                ->exists();

            if ($exists) {
                session()->flash('notification', [
                    'status'      => 'error',
                    'title'       => __('Error'),
                    'description' => __('Agent already assigned to one of the selected shops.'),
                ]);

                throw ValidationException::withMessages([
                    'shop_id' => __('Agent already assigned to one of the selected shops.'),
                ]);
            }

            ShopHasChatAgent::create([
                'organisation_id' => $organisationId,
                'shop_id'         => $shopId,
                'chat_agent_id'   => $agent->id,
            ]);
        }
    }

    public function asController(array $data, ChatAgent $agent)
    {
        return $this->handle($data, $agent);
    }

    public function update(array $data, ChatAgent $agent): void
    {

        $organisationId = $data['organisation_id'];
        $newShopIds     = $data['shop_id'] ?? [null];
        $newShopIds     = is_array($newShopIds) ? $newShopIds : [$newShopIds];

        $existing = ShopHasChatAgent::query()
            ->where('organisation_id', $organisationId)
            ->where('chat_agent_id', $agent->id)
            ->get();

        $existingShopIds = $existing
            ->pluck('shop_id')
            ->map(fn ($id) => $id ?? null)
            ->toArray();

        $toDelete = array_diff($existingShopIds, $newShopIds);

        if (!empty($toDelete)) {
            ShopHasChatAgent::query()
                ->where('organisation_id', $organisationId)
                ->where('chat_agent_id', $agent->id)
                ->whereIn('shop_id', $toDelete)
                ->delete();
        }

        $toInsert = array_diff($newShopIds, $existingShopIds);

        foreach ($toInsert as $shopId) {
            ShopHasChatAgent::create([
                'organisation_id' => $organisationId,
                'shop_id'         => $shopId,
                'chat_agent_id'   => $agent->id,
            ]);
        }
    }
}
