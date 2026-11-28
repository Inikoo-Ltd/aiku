<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use App\Enums\SysAdmin\McpOauthAttempt\McpOauthAttemptOutcomeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $client_name
 * @property McpOauthAttemptOutcomeEnum $outcome
 * @property string|null $error
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $created_at
 */
class McpOauthAttemptsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'created_at'    => $this->created_at,
            'username'      => $this->username,
            'client_name'   => $this->client_name,
            'outcome'       => $this->outcome->value,
            'outcome_label' => McpOauthAttemptOutcomeEnum::labels()[$this->outcome->value],
            'error'         => $this->error,
            'ip'            => $this->ip,
            'user_agent'    => $this->user_agent,
        ];
    }
}
