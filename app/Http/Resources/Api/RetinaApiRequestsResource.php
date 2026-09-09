<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string|null $route_name
 * @property string $method
 * @property string $path
 * @property array<array-key, mixed>|null $route_parameters
 * @property array<array-key, mixed>|null $payload
 * @property int $status
 * @property string|null $message
 * @property int|null $response_id
 * @property int|null $duration_ms
 * @property string|null $ip
 * @property \Illuminate\Support\Carbon $created_at
 */
class RetinaApiRequestsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'               => $this->id,
            'created_at'       => $this->created_at,
            'method'           => $this->method,
            'path'             => $this->path,
            'route_name'       => $this->route_name,
            'route_parameters' => $this->route_parameters,
            'payload'          => $this->payload,
            'status'           => $this->status,
            'is_success'       => $this->status < 400,
            'message'          => $this->message,
            'response_id'      => $this->response_id,
            'duration_ms'      => $this->duration_ms,
            'ip'               => $this->ip,
        ];
    }
}
