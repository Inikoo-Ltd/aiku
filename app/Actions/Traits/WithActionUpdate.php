<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

trait WithActionUpdate
{
    use AsAction;
    use WithAttributes;

    protected function extractJson($modelData, $field = ['data']): array
    {
        $data = [];
        foreach (Arr::dot(Arr::only($modelData, $field)) as $key => $value) {
            if (is_array($value)) {
                if (count($value) == 0) {
                    $value = null;
                } else {
                    $value = json_encode($value);
                }
            }

            if (str_contains($key, '.') || $value) {
                $data[str_replace('.', '->', $key)] = $value;
            }

        }

        return $data;
    }

    protected function update($model, $modelData, $jsonFields = [])
    {
        $model->update(
            Arr::except($modelData, $jsonFields)
        );
        $model->update($this->extractJson($modelData, $jsonFields));

        return $model;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
