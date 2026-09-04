<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string|null $contact_name
 * @property string|null $email
 * @property bool $status
 * @property float|null $share
 */
class JobPositionGuestsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'code'         => $this->code,
            'contact_name' => $this->contact_name,
            'email'        => $this->email,
            'share'        => $this->share === null ? null : percentage($this->share, 1),
            'status'       => $this->status ? [
                'tooltip' => __('active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
            ] : [
                'tooltip' => __('inactive'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
            ],
        ];
    }
}
