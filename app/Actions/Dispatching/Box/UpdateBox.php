<?php
/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Box;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Box;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateBox extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Box $box, array $modelData): Box
    {
        if(Arr::exists($modelData, 'height') || Arr::exists($modelData, 'depth') || Arr::exists($modelData, 'width')) {
            $dimension = Arr::get($modelData, 'height', $box->height) . 'x' . Arr::get($modelData, 'width', $box->width) . 'x' . Arr::get($modelData, 'depth', $box->depth);
            data_set($modelData, 'dimension', $dimension);
        }

        $box = $this->update($box, $modelData);

        return $box;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string'],
            'height'     => ['sometimes', 'numeric', 'min:0'],
            'width'      => ['sometimes', 'numeric', 'min:0'],
            'depth'      => ['sometimes', 'numeric', 'min:0'],
            'stock'      => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function asController(Box $box, ActionRequest $request): Box
    {
        $this->initialisation($box->organisation, $request);

        return $this->handle($box, $this->validatedData);
    }

    public function action(Box $box, array $modelData): Box
    {
        $this->asAction         = true;
        $this->initialisation($box->organisation, $modelData);

        return $this->handle($box, $this->validatedData);
    }


}
