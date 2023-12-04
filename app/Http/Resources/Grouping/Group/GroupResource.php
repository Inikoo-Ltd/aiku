<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Apr 2023 08:44:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Grouping\Group;

use App\Http\Resources\HasSelfCall;
use App\Models\Grouping\Group;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property $slug
 * @property $name
 * */
class GroupResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Group $user */
        $group = $this;

        return [
            'slug' => $group->slug,
            'name' => $group->name,
            'logo' => $group->logoImageSources(48, 48),
        ];
    }
}
