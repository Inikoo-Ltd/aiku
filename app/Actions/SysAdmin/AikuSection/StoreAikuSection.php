<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-11h-05m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\AikuSection;

use App\Actions\GrpAction;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Models\Analytics\AikuSection;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreAikuSection extends GrpAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Group $group, array $modelData): AikuSection
    {
        /** @var AikuSection $aikuSection */
        $aikuSection = $group->aikuSections()->create($modelData);
        $aikuSection->stats()->create();

        return $aikuSection;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', Rule::enum(AikuSectionEnum::class)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function action(Group $group, array $modelData): AikuSection
    {
        $this->initialisation($group, $modelData);

        return $this->handle($group, $modelData);
    }


}
