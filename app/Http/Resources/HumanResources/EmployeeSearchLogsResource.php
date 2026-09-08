<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $query
 * @property string|null $scope
 * @property int|null $results_count
 * @property string|null $clicked_url
 * @property string|null $clicked_at
 * @property string $created_at
 */
class EmployeeSearchLogsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'query'         => $this->query,
            'scope'         => $this->scope,
            'results_count' => $this->results_count,
            'clicked_url'   => $this->clicked_url,
            'clicked_at'    => $this->clicked_at,
            'created_at'    => $this->created_at,
        ];
    }
}
