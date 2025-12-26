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
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterBulkEditProductsResource;
use Lorisleiva\Actions\ActionRequest;

class GetSelectedMasterProductDetails extends GrpAction
{
    use WithMastersAuthorisation;

    public function handle(String $cacheKey, array $modelData): array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable
    {
        $masterProduct = MasterAsset::whereIn('id', $modelData['data'])->orderBy('created_at')->get();
        return MasterBulkEditProductsResource::collection($masterProduct)->toArray(request());
    }

    public function rules(): array
    {
        return [
            'data'  => ['sometimes', 'array']
        ];

    }

    public function asController(String $cacheKey, ActionRequest $request): \Illuminate\Http\Response|array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable
    {
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle($cacheKey, $this->validatedData);
    }

}
