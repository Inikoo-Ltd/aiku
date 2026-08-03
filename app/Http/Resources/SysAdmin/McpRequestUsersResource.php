<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $username
 * @property bool $sql_access
 * @property int $calls
 * @property int $errors
 * @property int $tools_used
 * @property int|null $avg_ms
 * @property string|null $last_used_at
 */
class McpRequestUsersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'username'     => $this->username,
            'sql_access'   => (bool) $this->sql_access,
            'calls'        => (int) $this->calls,
            'errors'       => (int) $this->errors,
            'tools_used'   => (int) $this->tools_used,
            'avg_ms'       => $this->avg_ms !== null ? (int) $this->avg_ms : null,
            'last_used_at' => $this->last_used_at,
        ];
    }
}
