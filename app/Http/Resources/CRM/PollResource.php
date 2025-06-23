<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\CRM;

use App\Enums\CRM\Poll\PollTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\CRM\Poll;

class PollResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Poll $poll */
        $poll = $this;
        return [
            'created_at'               => $poll->created_at,
            'id'                       => $poll->id,
            'slug'                     => $poll->slug,
            'name'                     => $poll->name,
            'label'                    => $poll->label,
            'position'                 => $poll->position,
            'type'                     => $poll->type,
            'in_registration'          => $poll->in_registration,
            'in_registration_required' => $poll->in_registration_required,
            'in_iris'                  => $poll->in_iris,
            'in_iris_required'         => $poll->in_iris_required,
            'options'                  => $poll->type == PollTypeEnum::OPTION ? PollOptionsResource::collection($poll->pollOptions) : [],
            // 'stats'                    => PollStatResource($poll->stats),
        ];
    }
}
