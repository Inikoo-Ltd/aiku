<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 May 2024 08:37:52 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string|null $about
 * @property bool $status
 * @property string $parent_type
 * @property string|null $contact_name
 * @property mixed $parent
 * @property \App\Models\SysAdmin\Group $group
 * @property \Illuminate\Database\Eloquent\Collection $authorisedOrganisations
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Spatie\Permission\Contracts\Role[] $roles
 * @property \Spatie\Permission\Contracts\Permission[] $permissions
 * @property mixed $number_current_api_tokens
 * @property mixed $number_expired_api_tokens
 */
class UsersResource extends JsonResource
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


        ];
    }
}
