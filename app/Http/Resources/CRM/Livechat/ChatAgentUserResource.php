<?php

namespace App\Http\Resources\CRM\Livechat;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $user_id
 * @property string $contact_name
 * @property string|null $alias
 * @property string|null $organisation_code
 */
class ChatAgentUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $name = $this->contact_name ?? $this->alias ?? 'Unnamed';

        return [
            'value' => $this->user_id,
            'label' => $this->organisation_code
                ? "{$this->organisation_code} | {$name}"
                : $name,
        ];
    }
}
