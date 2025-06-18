<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-15h-21m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\CRM\Poll;

class PollsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Poll $poll */
        $poll = $this;
        
        return [
            'id'                        => $poll->id,
            'slug'                      => $poll->slug,
            'name'                      => $poll->name,
            'label'                     => $poll->label,
            'position'                  => $poll->position,
            'type'                      => $poll->type,
            'in_registration'           => $poll->in_registration,
            'in_registration_required'  => $poll->in_registration_required,
            'in_iris'                   => $poll->in_iris,
            'in_iris_required'          => $poll->in_iris_required,
            'options'                   => PollOptionsResource::collection($poll->pollOptions)->toArray($request),
        ];
    }
}
