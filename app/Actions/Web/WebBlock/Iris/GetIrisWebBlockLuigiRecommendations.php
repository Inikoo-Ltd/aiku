<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-01m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockLuigiRecommendations
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        if (Arr::get($webBlock, 'type') === 'luigi-trends-1' && $this->isFamilyWebpage($webpage)) {
            return null;
        }

        if ($webpage->model instanceof Product) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.product.id', $webpage->model->id);
            data_set($webBlock, 'web_block.layout.data.fieldValue.product.luigi_identity', $webpage->model->getLuigiIdentity());
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue.recommendation_scope', $this->getRecommendationScope($webpage));

        return $webBlock;
    }

    private function isFamilyWebpage(Webpage $webpage): bool
    {
        return $webpage->model instanceof ProductCategory
            && $webpage->model->type === ProductCategoryTypeEnum::FAMILY;
    }

    /**
     * @return array<string, int>
     */
    private function getRecommendationScope(Webpage $webpage): array
    {
        $model = $webpage->model;

        if ($model instanceof Product) {
            return array_filter([
                'department_id' => $model->department_id,
            ]);
        }

        if ($model instanceof ProductCategory) {
            return match ($model->type) {
                ProductCategoryTypeEnum::DEPARTMENT     => ['department_id' => $model->id],
                ProductCategoryTypeEnum::SUB_DEPARTMENT => ['sub_department_id' => $model->id],
                ProductCategoryTypeEnum::FAMILY         => ['family_id' => $model->id],
                default                                 => [],
            };
        }

        return [];
    }
}
