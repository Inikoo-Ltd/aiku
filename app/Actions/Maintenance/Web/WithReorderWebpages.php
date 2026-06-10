<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait WithReorderWebpages
{
    public function reorderDepartmentPageBlocks(Webpage $webpage, $departmentWebBlockCode = 'department-description-1'): void
    {
        $departmentWebBlock = $this->getWebpageBlocksByType($webpage, $departmentWebBlockCode)->first()?->model_has_web_blocks_id;
        $departmentExtraDesc = null;

        // if ($departmentWebBlockCode == 'department-2') {
        //     $departmentExtraDesc = $this->getWebpageBlocksByType($webpage, 'department-2-extra-description')->first()->model_has_web_blocks_id;
        // }

        $subDepartmentBlock             = $this->getWebpageBlocksByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes())->first()?->model_has_web_blocks_id;
        $familiesBlock                  = $this->getWebpageBlocksByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes())->first()?->model_has_web_blocks_id;
        $relatedProductCategoryBlock    = $this->getWebpageBlocksByType($webpage, 'recommendation-product-category-from-master')->first()?->model_has_web_blocks_id;
        $webBlocks                      = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $reorderFamily = $subDepartmentBlock && $familiesBlock;
        $familyPosition = null;

        $count = $webpage->webBlocks()->count();
        $relatedProductCategoryPosition     = $count + 101;

        $runningPosition = 4;
        foreach ($webBlocks as $key => $position) {
            if ($key == $departmentWebBlock) {
                $webBlocks[$key] = 1;
            }elseif ($key == $subDepartmentBlock) {
                $webBlocks[$key] = 2;
            } elseif ($key == $departmentExtraDesc) {
                $webBlocks[$key] = 3;
            } elseif ($key == $relatedProductCategoryBlock) {
                $webBlocks[$key] = $relatedProductCategoryPosition;
            } else {
                $webBlocks[$key] = $runningPosition;

                if ($key == $subDepartmentBlock && $reorderFamily) {
                    $runningPosition++;
                    $familyPosition = $runningPosition;
                }

                $runningPosition++;
            }
        }

        if ($familyPosition) {
            $webBlocks[$familiesBlock] = $familyPosition;
        }

        foreach ($webBlocks as $key => $position) {
            DB::table('model_has_web_blocks')
                ->where('id', $key)
                ->update(['position' => $position]);
        }

        UpdateWebpageContent::run($webpage->refresh());
    }

    public function reorderFamilyPageBlocks(Webpage $webpage, $familyWebBlockCode = 'family-1'): void
    {
        $familyWebBlock = $this->getWebpageBlocksByType($webpage, $familyWebBlockCode)->first()->model_has_web_blocks_id;
        $familyExtraDesc = null;

        if ($familyWebBlockCode == 'family-2') {
            $familyExtraDesc = $this->getWebpageBlocksByType($webpage, 'family-2-extra-description')->first()->model_has_web_blocks_id;
        } elseif ($familyWebBlockCode == 'family-3') {
            $familyExtraDesc = $this->getWebpageBlocksByType($webpage, 'family-3-extra-description')->first()->model_has_web_blocks_id;
        }

        $website = $webpage->website;
        $liveProductsSnapshot = $website->liveProductsSnapshot;
        $unpublishedProductsSnapshot = $website->unpublishedProductsSnapshot;

        $usedWebBlockTemplateCodes = data_get($liveProductsSnapshot?->layout, 'code', data_get($unpublishedProductsSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes())));

        $productList = $this->getWebpageBlocksByType($webpage, $usedWebBlockTemplateCodes)->first()->model_has_web_blocks_id;

        $recommendationFromMaster   = $this->getWebpageBlocksByType($webpage, 'recommendation-from-master')->first()->model_has_web_blocks_id;
        $relatedProductCategory     = $this->getWebpageBlocksByType($webpage, 'recommendation-product-category-from-master')->first()->model_has_web_blocks_id;

        $trendsWebBlock             = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1')->first()->model_has_web_blocks_id;
        $lastBoughtWebBlock         = $this->getWebpageBlocksByType($webpage, 'recommendation-customer-recently-bought-1')->first()->model_has_web_blocks_id;
        $lastSeenWebBlock           = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1')->first()->model_has_web_blocks_id;

        $webBlocks = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $count = $webpage->webBlocks()->count();

        $recommendationFromMasterPosition   = $count + 101;
        $relatedProductCategoryPosition     = $count + 102;

        $trendsWebBlockPosition             = $count + 103;
        $lastBoughtWebBlockPosition         = $count + 104;
        $lastSeenWebBlockPosition           = $count + 105;

        $runningPosition = 4;
        foreach ($webBlocks as $key => $position) {
            if ($key == $familyWebBlock) {
                $webBlocks[$key] = 1;
            } elseif ($key == $productList) {
                $webBlocks[$key] = 2;
            } elseif ($key == $familyExtraDesc) {
                $webBlocks[$key] = 3;
            } elseif ($key == $recommendationFromMaster) {
                $webBlocks[$key] = $recommendationFromMasterPosition;
            } elseif ($key == $relatedProductCategory) {
                $webBlocks[$key] = $relatedProductCategoryPosition;
            } elseif ($key == $trendsWebBlock) {
                $webBlocks[$key] = $trendsWebBlockPosition;
            } elseif ($key == $lastSeenWebBlock) {
                $webBlocks[$key] = $lastSeenWebBlockPosition;
            } elseif ($key == $lastBoughtWebBlock) {
                $webBlocks[$key] = $lastBoughtWebBlockPosition;
            } else {
                $webBlocks[$key] = $runningPosition;
                $runningPosition++;
            }
        }

        foreach ($webBlocks as $key => $position) {
            DB::table('model_has_web_blocks')
                ->where('id', $key)
                ->update(['position' => $position]);
        }
        UpdateWebpageContent::run($webpage);
    }
}
