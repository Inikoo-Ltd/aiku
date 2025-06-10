<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-11h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageHasProductTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AttachProductToWebpage extends OrgAction
{
    public function handle(Webpage $webpage, Product $product, array $modelData): Product
    {
        data_set($data, 'organisation_id', $webpage->organisation_id);
        data_set($data, 'product_id', $product->id);
        data_set($data, 'group_id', $webpage->group_id);
        data_set($data, 'type', Arr::get($modelData, 'type'));

        $webpage->webpageHasProducts()->create($data);

        $webpage->refresh();
        $product->refresh();

        return $product;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(WebpageHasProductTypeEnum::class)],
        ];
    }

    public function action(Webpage $webpage, Product $product, array $modelData): Product
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $product, $this->validatedData);
    }
}
