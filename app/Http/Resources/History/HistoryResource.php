<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 11:57:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\History;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class HistoryResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $isStaff = $request->user() instanceof \App\Models\SysAdmin\User;

        return [
            'id'             => $this['id'],
            'ip_address'     => $isStaff ? $this['ip_address'] : null,
            'datetime'       => $this['created_at'],
            'url'            => $isStaff ? $this['url'] : null,
            'type'           => $this['type'],
            'organisation'   => $this['organisation_slug'],
            'old_values'     => $this['old_values'],
            'new_values'     => $this['new_values'],
            'value'          => '',
            'event'          => $this['event'],
            'comments'       => $this['comments'],
            'auditable_id'   => $this['auditable_id'],
            'auditable_type' => $this['auditable_type'],
            'record'         => $this['record'],
            'user_id'        => $this['user_id'],
            'user_type'      => $this['user_type'],
            'slug'           => $this['slug'],
            'user_agent'     => $isStaff ? $this['user_agent'] : null,
            'user_name'      => $this['user_type'] === __('WebUser') ? __('Customer') : $this->staffName(),
            'tags'           => $this['tags']
        ];
    }

    /**
     * A change made with someone else's access (help grant or borrowed permissions) shows both names: who did it, and for whom.
     */
    private function staffName(): string
    {
        $name       = $this->user?->contact_name ?? __('System');
        $onBehalfOf = data_get($this['data'], 'on_behalf_of_user_id');

        return $onBehalfOf
            ? __(':name for :other', ['name' => $name, 'other' => \App\Models\SysAdmin\User::find($onBehalfOf)?->contact_name ?? '#'.$onBehalfOf])
            : $name;
    }
}
