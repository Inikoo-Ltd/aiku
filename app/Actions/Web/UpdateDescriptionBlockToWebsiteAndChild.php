<?php

/*
 * author Louis Perez
 * created on 14-04-2026-13h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web;

use App\Actions\Maintenance\Web\WithRepairWebpages;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Events\BroadcastUpdateWeblocks;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDescriptionBlockToWebsiteAndChild
{
    use AsAction;
    use WithWebEditAuthorisation;
    use WithRepairWebpages;

    public function handle(Website $website, array $layouts, string $marginal): void
    {
        $marginalData = match($marginal) {
            'family_description'    => [
                'subType'   => 'family',
                'codes'     => WebBlockTemplateEnum::FAMILY_DESCRIPTION->templateCodes()
            ],
            'department_description'    => [
                'subType'   => 'family',
                'codes'     => WebBlockTemplateEnum::DEPARTMENT_DESCRIPTION->templateCodes()
            ],
            default                 => null
        };

        if (!$marginalData) {
            return;
        }

        $webpages = $website->webpages()
            ->where('sub_type', data_get($marginalData, 'subType'))
            ->orderBy('id');

        $progress = 0;
        $total = $webpages->clone()->count();
        $lastPercent = 0;

        foreach ($webpages->get() as $webpage) {
            Log::info("Web Slug: {$webpage->slug}");
            Log::info("Deleted WebBlockCode:", data_get($marginalData, 'codes'));
            $progress++;
            foreach (data_get($marginalData, 'codes') as $code) {
                $this->deleteWebBlocksByCode($webpage, $code);
            }

            foreach ($layouts as $code => $layout) {
                Log::info("Code: [$code]", $layout);
                $this->createWebBlock($webpage, $code, $layout);

                if ($marginal == 'department-description') {
                    if ($code == 'department-description-1') {
                        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes(), WebBlockTemplateEnum::LIST_PRODUCTS);
                        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes(), WebBlockTemplateEnum::SUB_DEPARTMENTS);
                        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes(), WebBlockTemplateEnum::FAMILIES);                    
                    } elseif ($code == 'department-description-2') {
                        foreach(WebBlockTemplateEnum::PRODUCT->templateCodes() as $productCode) {
                            $this->deleteWebBlocksByCode($webpage, $productCode);
                        }
                        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes(), WebBlockTemplateEnum::SUB_DEPARTMENTS);
                        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes(), WebBlockTemplateEnum::FAMILIES);
                    }
                }
            }


            $webpage->refresh();
            if ($webpage->sub_type === WebpageSubTypeEnum::FAMILY) {
                $this->setFamilyDescriptionIndex($webpage, collect(array_keys($layouts))->first(fn ($key) => !str_ends_with($key, '-extra-description')));
            }

            if ($webpage->sub_type === WebpageSubTypeEnum::DEPARTMENT) {
                $this->reorderDepartmentPageBlocks($webpage, true);
            }

            $webpage->refresh();
            $webpage->liveSnapshot?->updateQuietly(
                [
                    'layout'    => $webpage->unpublishedSnapshot->layout
                ]
            );
            if ($webpage->liveSnapshot) {
                $webpage->updateQuietly(
                    [
                        'published_layout'                => $webpage->liveSnapshot->layout,
                        'published_checksum'    => $webpage->liveSnapshot->published_checksum,
                        'is_dirty'           => false,
                    ]
                );
            }

            $percent = intval(($progress / $total) * 100);
            if ($percent >= $lastPercent + 10) {
                $lastPercent = $percent;
                BroadcastUpdateWeblocks::dispatch($percent, $website);
            }
        }

        BroadcastUpdateWeblocks::dispatch(100, $website);
    }

    public function setFamilyDescriptionIndex(Webpage $webpage, $familyWebBlockCode = 'family-1'): void
    {
        $familyWebBlock = $this->getWebpageBlocksByType($webpage, $familyWebBlockCode)->first()->model_has_web_blocks_id;
        $familyExtraDesc = null;

        if ($familyWebBlockCode == 'family-2') {
            $familyExtraDesc = $this->getWebpageBlocksByType($webpage, 'family-2-extra-description')->first()->model_has_web_blocks_id;
        }
        if ($familyWebBlockCode == 'family-3') {
            $familyExtraDesc = $this->getWebpageBlocksByType($webpage, 'family-3-extra-description')->first()->model_has_web_blocks_id;
        }

        $website = $webpage->website;
        $liveProductsSnapshot = $website->liveProductsSnapshot;
        $unpublishedProductsSnapshot = $website->unpublishedProductsSnapshot;

        $usedWebBlockTemplateCodes = data_get($liveProductsSnapshot?->layout, 'code', data_get($unpublishedProductsSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes())));

        $productList = $this->getWebpageBlocksByType($webpage, $usedWebBlockTemplateCodes)->first()?->model_has_web_blocks_id;
        
        $recommendationFromMaster   = $this->getWebpageBlocksByType($webpage, 'recommendation-from-master')->first()?->model_has_web_blocks_id;
        $relatedProductCategory     = $this->getWebpageBlocksByType($webpage, 'recommendation-product-category-from-master')->first()?->model_has_web_blocks_id;

        $trendsWebBlock     = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1')->first()?->model_has_web_blocks_id;
        $lastSeenWebBlock   = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1')->first()?->model_has_web_blocks_id;
        $lastBoughtWebBlock = $this->getWebpageBlocksByType($webpage, 'recommendation-customer-recently-bought-1')->first()?->model_has_web_blocks_id;


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

        $webpage->refresh();
        UpdateWebpageContent::run($webpage);
    }
}
