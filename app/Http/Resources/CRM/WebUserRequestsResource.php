<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\CRM;

use App\Actions\SysAdmin\WithLogRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $location
 * @property mixed $slug
 * @property mixed $username
 * @property mixed $ip_address
 * @property mixed $device
 * @property mixed $os
 * @property mixed $browser
 * @property mixed $route_name
 * @property mixed $route_params
 * @property mixed $date
 */
class WebUserRequestsResource extends JsonResource
{
    use WithLogRequest;
    public function toArray($request): array
    {
        $location = json_decode($this->location);
        return [
            'slug'        => $this->slug,
            'username'    => $this->username,
            'ip_address'  => $this->ip_address,
            'user_agent'      => array_filter([
                $this->device ? [
                    'tooltip' => $this->device,
                    'icon' => $this->getDeviceIcon($this->device)
                ] : null,
                $this->os ? [
                    'tooltip' => $this->os,
                    'icon'  => $this->getPlatformIcon($this->os)
                ] : null,
                $this->browser ? [
                    'tooltip' => $this->browser,
                    'icon'  => $this->getBrowserIcon($this->browser)
                ] : null,
            ]),
            'server'       => $location == ['localhost'],
            'location'      => $location,
            'url'      => '/' . ltrim(route($this->route_name, json_decode($this->route_params, true), false), '/'),
            'date'     => $this->date,
            ];
    }
}
