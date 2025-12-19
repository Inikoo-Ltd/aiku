<?php

/*
 * author Louis Perez
 * created on 19-12-2025-15h-30m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\GrpAction;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use Lorisleiva\Actions\ActionRequest;

class GetProductsBasedOnSavedCache extends GrpAction
{
    use WithMastersAuthorisation;

    public function handle(String $cacheKey, Array $modelData): LengthAwarePaginator
    {
        // Remove soon, just temporary since Cache is not used yet. Data will be taken from cache later
        $products = Product::whereIn('id', $modelData)->get();

        return $products;
    }

    // Remove soon, just temporary since Cache is not used yet
    public function rules(): Array
    {
        $rules = [
            'data'  => ['sometimes', 'array']
        ];

        return $rules;
    }

    public function asController(Organisation $organisation, String $cacheKey, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle($cacheKey, $this->validatedData);
    }

}
