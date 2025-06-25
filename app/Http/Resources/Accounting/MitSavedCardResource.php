<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Http\Resources\HasSelfCall;
use App\Models\Accounting\MitSavedCard;
use Illuminate\Http\Resources\Json\JsonResource;

class MitSavedCardResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var MitSavedCard $mitSavedCard */
        $mitSavedCard = $this;


        return [
            'id'               => $mitSavedCard->id,
            'token'            => '****',// Never expose the token,
            'last_four_digits' => $mitSavedCard->last_four_digits,
            'card_type'        => $mitSavedCard->card_type,
            'expires_at'       => $mitSavedCard->expires_at?->format('m/y'),
            'processed_at'     => $mitSavedCard->processed_at,

            'is_default'       => $mitSavedCard->priority === 1,
             'route_set_to_default' => [
                 'name' => 'retina.models.mit_saved_card.set_to_default',
                 'params' => [
                     'mitSavedCard' => $mitSavedCard->id,
                 ],
             ],
            'priority'       => $mitSavedCard->priority,
            'state'          => $mitSavedCard->state,
            'failure_status' => $mitSavedCard->failure_status,
            'failure_msg'    => $mitSavedCard->failure_status,
            'label'          => $mitSavedCard->label,
            'created_at'     => $mitSavedCard->created_at,
            'updated_at'     => $mitSavedCard->updated_at,

        ];
    }
}
