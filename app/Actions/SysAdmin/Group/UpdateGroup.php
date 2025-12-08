<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\SysAdmin\Group;

class UpdateGroup extends GrpAction
{
    use WithActionUpdate;

    public function handle(Group $group, array $modelData): Group
    {
        return $this->update($group, $modelData);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }

    public function action(Group $group, array $modelData): Group
    {
        $this->asAction = true;
        $this->initialisation($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    public function jsonResponse(Group $group): GroupResource
    {
        return new GroupResource($group);
    }
}
