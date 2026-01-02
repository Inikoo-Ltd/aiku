<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ShopHasChatAgent;
use Illuminate\Validation\ValidationException;

class AssignChatAgentToScope extends OrgAction
{
    public function handle(array $data, ChatAgent $agent): ShopHasChatAgent
    {

        $exists = ShopHasChatAgent::query()
            ->where('organisation_id', $data['organisation_id'])
            ->where('chat_agent_id', $agent->id)
            ->where(function ($q) use ($data) {
                if (! empty($data['shop_id'])) {
                    $q->where('shop_id', $data['shop_id']);
                } else {
                    $q->whereNull('shop_id');
                }
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'shop_id' => __('Agent already assigned to this organisation or shop.'),
            ]);
        }

        return ShopHasChatAgent::create([
            'organisation_id' => $data['organisation_id'],
            'shop_id' => $data['shop_id'] ?? null,
            'chat_agent_id' => $agent->id,
        ]);
    }

    public function asController(array $data, ChatAgent $agent): ShopHasChatAgent
    {
        return $this->handle($data, $agent);
    }
}
