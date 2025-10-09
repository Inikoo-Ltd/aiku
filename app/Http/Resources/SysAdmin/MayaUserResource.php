<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-12h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\SysAdmin;

use App\Http\Resources\SysAdmin\Organisation\MayaOrganisationResource;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class MayaUserResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var User $user */
        $user = $this;

        return [
            'id'                        => $this->id,
            'username'                  => $this->username,
            'image'                     => $user->imageSources(48, 48),
            'email'                     => $this->email,
            'about'                     => $user->about,
            'status'                    => match ($this->status) {
                true => [
                    'tooltip' => __('active'),
                    'icon'    => 'fal fa-check',
                    'class'   => 'text-green-500'
                ],
                default => [
                    'tooltip' => __('suspended'),
                    'icon'    => 'fal fa-times',
                    'class'   => 'text-red-500'
                ]
            },
            'number_current_api_tokens' => $this->number_current_api_tokens,
            'number_expired_api_tokens' => $this->number_expired_api_tokens,
            'parent_type'               => $this->parent_type,
            'contact_name'              => $this->contact_name,
            'authorised_organisations'  => MayaOrganisationResource::collection($user->authorisedOrganisations)->resolve(),
            'preferred_printer_id'      => Arr::get($user->settings, 'preferred_printer_id'),
        ];
    }
}
