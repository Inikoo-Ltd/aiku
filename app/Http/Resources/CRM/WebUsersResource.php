<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Feb 2024 12:51:58 Malaysia Time, Madrid Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $username
 * @property mixed $status
 * @property mixed $email
 * @property mixed $is_root
 * @property mixed $created_at
 * @property mixed $last_active
 * @property mixed $organisation_code
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $shop_slug
 * @property mixed $shop_code
 */
class WebUsersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'              => $this->slug,
            'username'          => $this->username,
            'email'             => $this->email,
            'created_at'        => $this->created_at,
            'organisation_code' => $this->organisation_code,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
        ];
    }
}
