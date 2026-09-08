<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $type
 * @property string $notes
 * @property string $workplace_slug
 * @property string $clocking_machine_slug
 */
class ClockingsResource extends JsonResource
{
    public function toArray($request): array
    {
        $clockedAt = $this->clocked_at;

        return [
            'id'                       => $this->id,
            'type'                     => $this->type,
            'notes'                    => $this->notes,
            'workplace_slug'           => $this->workplace_slug,
            'clocked_at'               => $clockedAt,
            'media_slug'               => $this->media_slug,
            'media_id'                 => $this->media_id,
            'photo'                    => $this->imageSources(),
            'clocking_machine_slug'    => $this->clocking_machine_slug,
            'employee_name'            => $this->employee_name,
            'clocking_machine_name'    => $this->clocking_machine_name,
            'clocking_machine_qr_code' => $this->clocking_machine_qr_code,
            'delete_route'             => [
                'name'       => 'grp.models.clocking-machine.clocking.delete',
                'parameters' => ['clocking' => $this->id],
            ],
        ];
    }
}
