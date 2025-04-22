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
    public function handle(Organisation $organisation, $modelData): array
    {
        $with = [];
        if ($withArgs = Arr::get($modelData, 'with', '')) {
            $with = explode(',', $withArgs);
        }

        $id = Arr::get($modelData, 'id');

        $dispatch = Arr::get($modelData, 'bg', false);

        if ($dispatch) {
            $delay = (int)Arr::get($modelData, 'delay', 0);

            (new $this->fetcher())::dispatch(
                $organisation->id,
                $id,
                $with,
                Arr::get($modelData, 'fetch_stack_id')
            )->delay($delay);

            return [
                'organisation' => $organisation->slug,
                'model'        => class_basename($this->fetcher),
                'id'           => $id,
                'date'         => now('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s')
            ];
        } else {
            $model = (new $this->fetcher())::make()->action($organisation->id, $id, $with);
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


    public function rules(): array
    {
        return [
            'id'             => ['required', 'integer'],
            'with'           => ['sometimes', 'string'],
            'bg'             => ['sometimes', 'boolean'],
            'delay'          => ['sometimes', 'integer'],
            'fetch_stack_id' => ['sometimes', 'integer']
        ];
    }


    public function action(Organisation $organisation, array $modelData): array
    {
        $this->asAction = true;
        $fetcher        = $this->fetcher;// hack to avoid reset of attributes in initialisation
        $this->initialisation($organisation, $modelData);
        $this->fetcher = $fetcher;

        return $this->handle($organisation, $this->validatedData);
    }


    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }
}
