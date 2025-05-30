<?php
/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasContent;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ReorderModelHasContent extends OrgAction
{
    public function handle(Product|ProductCategory $parent, array $modelData)
    {
        $positions = Arr::get($modelData, 'positions', []);

        foreach ($positions as $id => $newPosition) {
            $content = $parent->contents()->find($id);

            if ($content) {
                $content->update(['position' => $newPosition]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'positions' => ['required', 'array']
        ];
    }
}
