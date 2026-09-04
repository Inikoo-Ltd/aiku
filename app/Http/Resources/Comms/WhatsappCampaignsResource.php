<?php

namespace App\Http\Resources\Comms;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string|null $template_name
 */
class WhatsappCampaignsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var WhatsappCampaign $campaign */
        $campaign = $this;

        return [
            'id'               => $campaign->id,
            'slug'             => $campaign->slug,
            'name'             => $campaign->name,
            'template_name'    => $this->template_name,
            'state'            => $campaign->state,
            'state_label'      => $campaign->state->labels()[$campaign->state->value],
            'state_icon'       => $campaign->state->stateIcon()[$campaign->state->value],
            'type'             => $campaign->type,
            'type_label'       => $campaign->type->labels()[$campaign->type->value],
            'recipients_count' => $campaign->recipients_count,
            'scheduled_at'     => $campaign->scheduled_at?->format('d F Y, H:i'),
            'sent_at'          => $campaign->sent_at?->format('d F Y, H:i'),
            'created_at'       => $campaign->created_at?->format('d F Y, H:i'),
        ];
    }
}
