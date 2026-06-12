<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Jun 2026 13:57:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection as CollectionModel;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebpageAlt
{
    use AsAction;

    public function handle(Webpage|int $webpage): ?string
    {
        if (is_int($webpage)) {
            $webpage = Webpage::find($webpage);
        }

        if (!$webpage) {
            return null;
        }

        $alt = null;

        if ($this->canUseWebpageModelForAlt($webpage)) {
            $title = $webpage->title;
            if (!blank($title)) {
                $alt = $title;
                dd($alt);
            }
        }

        // Fallback to model name
        if (blank($alt) && !blank($webpage->model?->name)) {
            $alt = $webpage->model?->name;
        }

        // Sanitize alt from HTML tags and entities
        if (!blank($alt)) {
            $alt = strip_tags($alt);
            $alt = html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $alt = preg_replace('/\s+/', ' ', $alt);
            $alt = trim($alt);
        }

        return $alt;
    }

    private function canUseWebpageModelForAlt(Webpage $webpage): bool
    {
        $model = $webpage->model;

        if ($model instanceof Product || $model instanceof CollectionModel) {
            return true;
        }

        return $model instanceof ProductCategory
            && in_array($model->type, [
                ProductCategoryTypeEnum::DEPARTMENT,
                ProductCategoryTypeEnum::SUB_DEPARTMENT,
                ProductCategoryTypeEnum::FAMILY,
            ], true);
    }
}
