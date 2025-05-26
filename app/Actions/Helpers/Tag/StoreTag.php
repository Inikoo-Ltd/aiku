<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

class StoreTag extends OrgAction
{
    public function handle(Group $group, array $modelData): Tag
    {
        data_set($modelData, 'group_id', $group->id);

        return Tag::create($modelData);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        $this->handle($group, $this->validatedData);
    }

}
