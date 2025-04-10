<?php

/*
 * author Arya Permana - Kirin
 * created on 10-04-2025-10h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\SysAdmin;

use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class SupervisorUsersResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var User $user */
        $user = $this;

        return [
            'id'            => $user->id,
            'username'      => $user->username,
            'image'         => $user->imageSources(48, 48),
            'email'         => $user->email,
            'status'        => match ($user->status) {
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
            'parent_type'   => $user->parent_type,
            'contact_name'  => $user->contact_name,
        ];
    }
}
