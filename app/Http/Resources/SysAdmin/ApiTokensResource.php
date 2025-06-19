<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class ApiTokensResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'last_used_at'       => $this->last_used_at,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'expires_at'         => $this->expires_at,
            'route_delete_token' => [
                'name'       => 'grp.models.access_token.delete',
                'parameters' => [
                    'token' => $this->id
                ]
            ]
        ];
    }
}
