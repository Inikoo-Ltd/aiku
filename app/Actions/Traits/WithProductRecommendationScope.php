<?php

/*
 * Author: Vika Aqordi
 * Created on 14-09-2026-10h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;

trait WithProductRecommendationScope
{
    /**
     * The catalogue reach a recommender gets on a given webpage.
     *
     * @return array<string, int>
     */
    public function getRecommendationScope(Webpage $webpage): array
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

    public function isFamilyWebpage(Webpage $webpage): bool
    {
        return $webpage->model instanceof ProductCategory
            && $webpage->model->type === ProductCategoryTypeEnum::FAMILY;
    }
}
