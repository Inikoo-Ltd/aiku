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

class EmailTrackingEventResource extends JsonResource
{
    use WithLogRequest;

    public function toArray($request): array
    {
        return [
            'ip' => $this->ip,
            'type' => $this->type->typeIcon()[$this->type->value],
            'date' => $this->date,
            'device' => $this->device ? [
                'tooltip' => __($this->device),
                'icon' => $this->getDeviceIcon($this->device),
            ] : [],
        ];
    }
}
