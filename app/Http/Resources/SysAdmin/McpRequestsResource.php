<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $tool
 * @property array<array-key, mixed>|string $arguments
 * @property bool $is_error
 * @property int|null $duration_ms
 * @property string $created_at
 * @property string|null $username
 * @property bool|null $can_use_mcp_sql
 */
class McpRequestsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'tool'        => $this->tool,
            'arguments'   => is_string($this->arguments) ? json_decode($this->arguments, true) : $this->arguments,
            'is_error'    => (bool) $this->is_error,
            'duration_ms' => $this->duration_ms,
            'created_at'  => $this->created_at,
            'username'    => $this->username,
            'sql_access'  => (bool) $this->can_use_mcp_sql,
        ];
    }
}
