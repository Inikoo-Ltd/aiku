<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Feb 2025 13:10:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithProcessAurora
{
    public function rules(): array
    {
        return [
            'id'   => ['required', 'integer'],
            'with' => ['sometimes', 'string'],
            'bg'   => ['sometimes', 'boolean'],
            'delay' => ['sometimes', 'integer']
        ];
    }


    /**
     * @throws \Exception
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $validatedData = $this->validatedData;

        $with = [];
        if ($withArgs = Arr::get($validatedData, 'with', '')) {
            $with = explode(',', $withArgs);
        }


        if (Arr::get($validatedData, 'bg', false)) {


            $delay = (int) Arr::get($validatedData, 'delay', 0);

            (new $this->fetcher())::dispatch($organisation->id, Arr::get($validatedData, 'id'), $with)->delay($delay);

            return [
                'organisation' => $organisation->slug,
                'model'        => class_basename($this->fetcher),
                'id'           => Arr::get($validatedData, 'id'),
                'date'         => now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s')
            ];
        } else {
            $model = (new $this->fetcher())::make()->action($organisation->id, Arr::get($validatedData, 'id'), $with);

            if ($model) {
                return [
                    'status' => 'ok',
                    'type'   => 'foreground',
                    'model'  => class_basename($model),
                    'id'     => $model->id
                ];
            } else {
                return [
                    'status' => 'error',
                    'type'   => 'foreground'
                ];
            }
        }
    }
}
