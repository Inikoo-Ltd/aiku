<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Feb 2025 17:16:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora\Api;

use App\Actions\CRM\Favourite\UnFavourite;
use App\Actions\OrgAction;
use App\Models\CRM\Favourite;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property string $fetcher
 */
class ProcessAuroraDeleteFavourites extends OrgAction
{
    use WithProcessAurora;

    public function rules(): array
    {
        return [
            'id'              => ['required', 'integer'],
            'unfavourited_at' => ['required', 'date'],
            'bg'              => ['sometimes', 'boolean'],
            'delay'           => ['sometimes', 'integer']
        ];
    }


    public function action(Organisation $organisation, array $modelData): array
    {
        $this->asAction = true;
        $fetcher        = $this->fetcher;// hack to avoid reset of attributes in initialisation
        $this->initialisation($organisation, $modelData);
        $this->fetcher = $fetcher;
        $validatedData = $this->validatedData;

        $favourite = Favourite::where('source_id', $organisation->id.':'.$validatedData['id'])->first();
        $res = [
            'status'  => 'error',
            'message' => 'Favourite not found',
            'model'   => 'DeleteFavourite'
        ];
        if ($favourite) {
            UnFavourite::make()->action($favourite, Arr::only($validatedData, 'unfavourited_at'));
            $res = [
                'status' => 'ok',
                'id'     => $favourite->source_id,
                'model'  => 'DeleteFavourite',
            ];
        }

        return $res;

    }


    /**
     * @throws \Exception
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $res = [
            'status'  => 'error',
            'message' => 'Favourite not found',
            'model'   => 'DeleteFavourite'
        ];

        $this->initialisation($organisation, $request);
        $validatedData = $this->validatedData;

        $favourite = Favourite::where('source_id', $organisation->id.':'.$validatedData['id'])->first();

        if ($favourite) {
            UnFavourite::make()->action($favourite, Arr::only($validatedData, 'unfavourited_at'));
            $res = [
                'status' => 'ok',
                'id'     => $favourite->source_id,
                'model'  => 'DeleteFavourite',
            ];
        }

        return $res;
    }

}
