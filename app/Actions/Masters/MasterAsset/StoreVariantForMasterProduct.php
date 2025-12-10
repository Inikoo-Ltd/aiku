<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;

;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class StoreVariantForMasterProduct extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, $modelData): MasterAsset
    {
        dd($modelData);
    }

    public function rules(): array
    {
        return ['*' => []];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $data = $request->all();

        return $this->handle($masterAsset, $data);
    }
}
