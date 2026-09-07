<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * @property int $id
 * @property string $name
 * @property string $scope_type
 * @property int $scope_id
 * @property string|null $scope_name
 * @property int $number_users
 * @property int $number_permissions
 */
class JobPositionRolesResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'label'              => $this->label(),
            'scope_type'         => $this->scope_type,
            'scope_name'         => $this->scope_name,
            'number_users'       => (int) $this->number_users,
            'number_permissions' => (int) $this->number_permissions,
        ];
    }

    /**
     * Role names are stored suffixed with the id of the model they are scoped to,
     * as built by RolesEnum::getRoleName().
     */
    private function label(): string
    {
        $rawName = preg_replace('/-\d+$/', '', $this->name);

        return RolesEnum::tryFrom($rawName)?->label() ?? Str::headline($rawName);
    }
}
