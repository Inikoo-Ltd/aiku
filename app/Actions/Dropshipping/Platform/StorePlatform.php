<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\GrpAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePlatform extends GrpAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Group $group, array $modelData): Platform
    {
        /** @var Platform $platform */
        $platform = $group->platforms()->create($modelData);
        $platform->stats()->create();

        return $platform;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'string',
                'required',
                new IUnique(
                    table: 'platforms',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
                'max:64'

            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PlatformTypeEnum::class)],
        ];
    }

    public function action(Group $group, array $modelData): Platform
    {
        $this->initialisation($group, $modelData);

        return $this->handle($group, $modelData);
    }


}
