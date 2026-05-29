<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait WithReorderWebpages
{
    public function reorderDepartmentPageBlocks(Webpage $webpage, $setDescriptionTop = false): void
    {
        $departmentDescriptionWebBlock  = $this->getWebpageBlocksByType($webpage, 'department-description-1')->first()->model_has_web_blocks_id;
        $subDepartmentBlock             = $this->getWebpageBlocksByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes())->first()?->model_has_web_blocks_id;
        $familiesBlock                  = $this->getWebpageBlocksByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes())->first()?->model_has_web_blocks_id;
        $relatedProductCategoryBlock    = $this->getWebpageBlocksByType($webpage, 'recommendation-product-category-from-master')->first()?->model_has_web_blocks_id;
        $webBlocks                      = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $runningPosition = 1;
        if ($setDescriptionTop) {
            $runningPosition = 2;
        }

        $reorderFamily = $subDepartmentBlock && $familiesBlock;
        $familyPosition = null;
        $relatedProductCategoryBlockPosition = 101;

        foreach ($webBlocks as $key => $position) {
            if ($key == $departmentDescriptionWebBlock && $setDescriptionTop) {
                $webBlocks[$key] = 1;
            } elseif ($key == $relatedProductCategoryBlock) {
                $webBlocks[$key] = $relatedProductCategoryBlockPosition;
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
}
