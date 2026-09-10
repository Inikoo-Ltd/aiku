<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Mail;

use App\Actions\SysAdmin\WithLogRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class EmailTrackingEventResource extends JsonResource
{
    use WithLogRequest;
    public function toArray($request): array
    {
        $url = Arr::get($this->data, 'l');
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $parameters);

        return [
            'url' => $url,
            'label' => $url ? (string) parse_url($url, PHP_URL_HOST).(string) parse_url($url, PHP_URL_PATH) : null,
            'element' => Arr::get($parameters, 'utm_content'),
            'ip' => $this->ip,
            'type' => $this->type->typeIcon()[$this->type->value],
            'date' => $this->date,
            'device' => $this->device ? [
                'tooltip' => __($this->device),
                'icon' => $this->getDeviceIcon($this->device)
            ] : [],
        ];
    }
}
