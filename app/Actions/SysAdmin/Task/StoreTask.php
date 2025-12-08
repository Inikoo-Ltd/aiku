<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Task;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreTask extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Organisation|Shop|Employee $parent, $modelData): Task
    {
        data_set($modelData, 'group_id', $parent->group->id);
        if ($parent instanceof Organisation) {
            data_set($modelData, 'organisation_id', $parent->id);
        } else {
            data_set($modelData, 'organisation_id', $parent->organisation->id);
        }

        $task = Task::create($modelData);

        return $task;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:64', 'unique:tasks,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
        ];
    }

    public function asController(Organisation|Shop|Employee $parent): Task
    {
        return $this->handle($parent, $this->validatedData);
    }
}
