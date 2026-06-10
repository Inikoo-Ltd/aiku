<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:17:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Arr;

class GetIrisWebBlockFamilies
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $hasOverviewPage = false;

        $limit = data_get(
            $webBlock,
            'web_block.layout.data.fieldValue.department.number_visible',
            20
        );

        if ($webpage->model instanceof ProductCategory) {

            if ($webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT && $webpage->layout_style == 'main_page') {
                $model = $webpage->model;

                $hasOverviewPage = $model->webpages()->where('layout_style', 'families-overview')->exists();
            }

            $yearlySalesSubquery = DB::table('product_category_time_series_records as ptsr')
                ->join('product_category_time_series as pts', 'ptsr.product_category_time_series_id', '=', 'pts.id')
                ->where('pts.frequency', 'yearly')
                ->where('ptsr.frequency', 'Y')
                ->selectRaw('pts.product_category_id, SUM(ptsr.sales_org_currency_external) as total_sales')
                ->groupBy('pts.product_category_id');

            $families = DB::table('product_categories')
                ->leftJoin('webpages', function ($join) {
                    $join->on('product_categories.id', '=', 'webpages.model_id')
                        ->where('webpages.model_type', 'ProductCategory');
                })
                ->leftJoinSub($yearlySalesSubquery, 'yearly_sales', 'yearly_sales.product_category_id', '=', 'product_categories.id')
                ->select(['product_categories.code', 'product_categories.web_images', 'product_categories.offers_data', 'name', 'image_id', 'webpages.url', 'webpages.canonical_url', 'title'])
                ->selectRaw('\''.request()->path().'\' as parent_url')
                ->where(function ($query) use ($webpage) {
                    if ($webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT) {
                        $query->where('product_categories.department_id', $webpage->model_id)
                            ->orWhereIn('product_categories.id', function ($sub) use ($webpage) {
                                $sub->select('chm.model_id')
                                    ->from('collection_has_models as chm')
                                    ->where('chm.model_type', 'ProductCategory')
                                    ->whereIn('chm.collection_id', function ($sub2) use ($webpage) {
                                        $sub2->select('mhc.collection_id')
                                            ->from('model_has_collections as mhc')
                                            ->where('mhc.model_id', $webpage->model_id);
                                    });
                            });
                    } else {
                        $query->where('product_categories.sub_department_id', $webpage->model_id);
                    }
                })
                ->where('product_categories.shop_id', $webpage->shop_id)
                ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
                ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
                ->where('show_in_website', true)
                ->whereNotNull('webpages.id')
                ->where('webpages.state', WebpageStateEnum::LIVE->value)
                ->whereNull('product_categories.deleted_at')
                ->whereNull('webpages.deleted_at')
                ->when(
                    $hasOverviewPage,
                    function ($query) use ($limit) {
                        $query->limit($limit);
                    }
                )->orderByRaw('yearly_sales.total_sales DESC NULLS LAST')
                ->get();
        } elseif ($webpage->model instanceof Collection) {
            $yearlySalesSubquery = DB::table('product_category_time_series_records as ptsr')
                ->join('product_category_time_series as pts', 'ptsr.product_category_time_series_id', '=', 'pts.id')
                ->where('pts.frequency', 'yearly')
                ->where('ptsr.frequency', 'Y')
                ->selectRaw('pts.product_category_id, SUM(ptsr.sales_org_currency_external) as total_sales')
                ->groupBy('pts.product_category_id');

            $families = DB::table('product_categories')
                ->leftJoin('collection_has_models', function ($join) {
                    $join->on('collection_has_models.model_id', '=', 'product_categories.id')
                        ->where('collection_has_models.model_type', '=', 'ProductCategory');
                })
                ->leftJoin('webpages', function ($join) {
                    $join->on('product_categories.id', '=', 'webpages.model_id')
                        ->where('webpages.model_type', '=', 'ProductCategory');
                })
                ->leftJoinSub($yearlySalesSubquery, 'yearly_sales', 'yearly_sales.product_category_id', '=', 'product_categories.id')
                ->select(['product_categories.code', 'product_categories.name', 'product_categories.image_id', 'product_categories.web_images', 'product_categories.offers_data', 'webpages.url', 'webpages.canonical_url', 'title'])
                ->selectRaw('\''.request()->path().'\' as parent_url')
                ->where('collection_has_models.collection_id', $webpage->model_id)
                ->where('product_categories.shop_id', $webpage->shop_id)
                ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
                ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
                ->where('show_in_website', true)
                ->whereNull('product_categories.deleted_at')
                ->whereNull('webpages.deleted_at')
                ->orderByRaw('yearly_sales.total_sales DESC NULLS LAST')
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
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($families)->toArray(request()));
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
