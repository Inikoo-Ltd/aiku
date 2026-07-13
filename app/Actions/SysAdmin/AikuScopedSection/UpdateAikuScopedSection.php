<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-14h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\AikuScopedSection;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Analytics\AikuScopedSection;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateAikuScopedSection extends GrpAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(AikuScopedSection $aikuScopedSection, array $modelData): AikuScopedSection
    {
        $aikuScopedSection = $this->update($aikuScopedSection, $modelData);
        return $aikuScopedSection;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function action(AikuScopedSection $aikuScopedSection, array $modelData)
    {
        $this->initialisation($aikuScopedSection->group, $modelData);

        return $this->handle($aikuScopedSection, $modelData);
    }


}
