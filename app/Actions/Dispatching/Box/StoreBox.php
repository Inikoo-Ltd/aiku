<?php
/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Box;

use App\Actions\OrgAction;
use App\Models\Dispatching\Box;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBox extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Organisation $organisation, array $modelData): Box
    {
        $dimension = $modelData['height'] . 'x' . $modelData['width'] . 'x' . $modelData['depth'];
        data_set($modelData, 'dimension', $dimension);
        data_set($modelData, 'group_id', $organisation->group_id);
        $box = $organisation->boxes()->create($modelData);

        return $box;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string'],
            'height'     => ['required', 'numeric', 'min:0'],
            'width'      => ['required', 'numeric', 'min:0'],
            'depth'      => ['required', 'numeric', 'min:0'],
            'stock'      => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): Box
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): Box
    {
        $this->asAction         = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }


}
