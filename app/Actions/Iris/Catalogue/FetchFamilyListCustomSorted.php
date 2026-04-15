<?php

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class FetchFamilyListCustomSorted extends IrisAction
{
    private Webpage $webpage;

    public function handle(ProductCategory $productCategory, array $modelData)
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
        });

        $families = QueryBuilder::for(ProductCategory::class)
                ->leftJoin('webpages', function ($join) {
                    $join->on('product_categories.id', '=', 'webpages.model_id')
                        ->where('webpages.model_type', 'ProductCategory');
                })
                ->select(['product_categories.code', 'product_categories.web_images','product_categories.offers_data', 'name', 'image_id', 'webpages.url', 'webpages.canonical_url', 'title'])
                ->selectRaw('\''.request()->path().'\' as parent_url')
                ->where(function ($query) {
                    if ($this->webpage->sub_type == WebpageSubTypeEnum::DEPARTMENT) {
                        $query->where('product_categories.department_id', $this->webpage->model_id)
                            ->orWhereIn('product_categories.id', function ($sub) {
                                $sub->select('chm.model_id')
                                    ->from('collection_has_models as chm')
                                    ->where('chm.model_type', 'ProductCategory')
                                    ->whereIn('chm.collection_id', function ($sub2) {
                                        $sub2->select('mhc.collection_id')
                                            ->from('model_has_collections as mhc')
                                            ->where('mhc.model_id', $this->webpage->model_id);
                                    });
                            });
                    } else {
                        $query->where('product_categories.sub_department_id', $this->webpage->model_id);
                    }
                })
                ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
                ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
                ->where('show_in_website', true)
                ->whereNotNull('webpages.id')
                ->where('webpages.state', WebpageStateEnum::LIVE->value)
                ->whereNull('product_categories.deleted_at')
                ->whereNull('webpages.deleted_at');

        return $families
            ->allowedSorts(['amount', 'running_amount', 'type', 'created_at','payment_reference'])
            ->allowedFilters([$globalSearch])
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $families)
    {
        return JsonResource::collection($families);
    }

    public function asController(Webpage $webpage, ProductCategory $productCategory, ActionRequest $request)
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        return $this->handle($productCategory, $this->validatedData);
    }

}