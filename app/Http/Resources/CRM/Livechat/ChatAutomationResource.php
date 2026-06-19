<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatAutomationResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $automation = $this;
        $trigger    = $automation->trigger_type instanceof ChatAutomationTriggerEnum
            ? $automation->trigger_type
            : ChatAutomationTriggerEnum::from($automation->trigger_type);

        return [
            'id'                 => $automation->id,
            'shop_id'            => $automation->shop_id,
            'shop_name'          => $automation->relationLoaded('shop') && $automation->shop ? $automation->shop->name : null,
            'name'               => $automation->name,
            'trigger_type'       => $trigger->value,
            'trigger_label'      => ChatAutomationTriggerEnum::labels()[$trigger->value] ?? $trigger->value,
            'is_enabled'         => (bool) $automation->is_enabled,
            'message'            => $automation->message,
            'flow'               => $automation->flow ?? null,
            'conditions'         => $automation->conditions ?? [],
            'priority'           => $automation->priority,
            'send_once'          => (bool) $automation->send_once,
            'sent_count'         => $automation->stats['sent_count'] ?? 0,
            'created_at'         => $automation->created_at,
            'updated_at'         => $automation->updated_at,
        ];
    }
}
