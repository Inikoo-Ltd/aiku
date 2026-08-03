<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:17:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithFamiliesQuery;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Arr;

class GetIrisWebBlockFamilies
{
    use AsObject;
    use WithFamiliesQuery;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $limit = data_get(
            $webBlock,
            'web_block.layout.data.fieldValue.department.number_visible',
            20
        );

        if ($webpage->model instanceof ProductCategory || $webpage->model instanceof Collection) {
            $hasOverviewPage = false;

            if ($webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT && $webpage->layout_style == 'main_page') {
                $model = $webpage->model;

                $hasOverviewPage = $model->webpages()->where('layout_style', 'families-overview')->exists();
            }

            $families = $this->getFamilyList($webpage)
                ->when(
                    $hasOverviewPage,
                    function ($query) use ($limit) {
                        $query->limit($limit);
                    }
                )
                ->get();
        } else {
            return $webBlock;
        }

        $productRoute = [
            'iris'     => [
                'name'       => 'iris.json.product_category.products.index',
                'parameters' => [$webpage->model->slug],
            ],
        ];


        $model = $webpage->model;
        $overview_url = null;
        if ($model->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $overview_url = $model->webpages()->where('webpages.layout_style', 'families-overview')->first()?->url;
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['family']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);
        data_set($webBlock, 'web_block.layout.data.fieldValue.show_overview_button', $hasOverviewPage);
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($families)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.webpage_data.webpage_type', $model->type);
        data_set($webBlock, 'web_block.layout.data.fieldValue.webpage_data.overview_url', $overview_url);

        return [
            'type' => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }

}
