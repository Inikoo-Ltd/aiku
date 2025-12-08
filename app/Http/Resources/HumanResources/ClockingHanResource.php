<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 May 2024 19:06:06 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Clocking;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property mixed $clocked_at
 */
class ClockingHanResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Clocking $clocking */
        $clocking = $this;

        return [
            'id' => $clocking->id,
            'clocked_at' => $clocking->clocked_at,
            'photo' => $clocking->imageSources(),
        ];
    }
}
