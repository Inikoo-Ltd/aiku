<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Apr 2023 08:44:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin\Group;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\SysAdmin\Group;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;

        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'label' => $group->name,
            'logo' => $request instanceof Website ? $this->group->imageSources(48, 48) : $this->imageSources(48, 48),
            'currency' => CurrencyResource::make($request instanceof Website ? $group->organisation->currency : $group->currency)->getArray(),
        ];
    }
}
