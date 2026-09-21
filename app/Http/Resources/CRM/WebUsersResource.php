<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Feb 2024 12:51:58 Malaysia Time, Madrid Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
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
 * @property mixed $contact_name
 * @property mixed $status
 * @property mixed $last_login_at
 * @property mixed $number_logins
 * @property mixed $number_failed_logins
 * @property mixed $last_failed_login_at
 */
class WebUsersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                   => $this->slug,
            'username'               => $this->username,
            'email'                  => $this->email,
            'contact_name'           => $this->contact_name,
            'is_root'                => $this->is_root ? [
                'tooltip' => __('Admin'),
                'icon'    => 'fal fa-user-crown',
            ] : [],
            'status'                 => $this->status ? [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',
            ] : [
                'tooltip' => __('Blocked'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-red-500',
            ],
            'last_login_at'          => $this->last_login_at,
            'number_logins'          => $this->number_logins ?? 0,
            'number_failed_logins'   => $this->number_failed_logins ?? 0,
            'last_failed_login_at'   => $this->last_failed_login_at,
            'created_at'        => $this->created_at,
            'organisation_code' => $this->organisation_code,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
            'shop_type'       => $this->shop_type,
            'delete_route' => $this->shop_type == ShopTypeEnum::FULFILMENT->value
                || $request->user()?->authTo("supervisor-crm.{$this->shop_id}") ? [
                'name' => 'grp.models.web-user.delete',
                'parameters' => [
                    'webUser' => $this->id
                ]
            ] : null
        ];
    }
}
