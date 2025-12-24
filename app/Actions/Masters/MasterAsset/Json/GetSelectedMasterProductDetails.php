<?php

/*
 * author Louis Perez
 * created on 19-12-2025-15h-30m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterBulkEditProductsResource;
use Lorisleiva\Actions\ActionRequest;


class GetSelectedMasterProductDetails extends GrpAction
{
    use WithMastersAuthorisation;

    public function handle(String $cacheKey, Array $modelData)
    {
        // Remove soon, just temporary since Cache is not used yet. Data will be taken from cache later
        $masterProduct = MasterAsset::whereIn('id', $modelData['data'])->orderBy('created_at')->get();
        return MasterBulkEditProductsResource::collection($masterProduct)->toArray(request());
    }

    // Remove soon, just temporary since Cache is not used yet
    public function rules(): Array
    {
        $rules = [
            'data'  => ['sometimes', 'array']
        ];

        return $rules;
    }

    public function asController(String $cacheKey, ActionRequest $request)
    {
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle($cacheKey, $this->validatedData);
    }

}
