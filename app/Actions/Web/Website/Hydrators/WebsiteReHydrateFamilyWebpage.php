<?php

/*
 * author Louis Perez
 * created on 17-03-2026-09h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Website\Hydrators;

use App\Actions\Maintenance\Web\WithRepairWebpages;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteReHydrateFamilyWebpage implements ShouldBeUnique
{
    use AsAction;
    use WithRepairWebpages;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    public function handle(Website $website): void
    {
        $chosenOption = data_get($website->shop->settings, 'website.family_webpage_split_description', false) ? 'family-2' : 'family-1';
        $webpages = $website->webpages()
            ->where('sub_type', 'family')
            ->orderBy('id')
            ->get();
        
        foreach ($webpages as $webpage) {
            $this->rehydrateWebBlock($webpage, $chosenOption);
        }
    }

    public function rehydrateWebBlock(Webpage $webpage, $familyDescriptionBlock = 'family-1')
    {
        if($familyDescriptionBlock == 'family-1') {
            $countFamilyDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'family-1');
            if (count($countFamilyDescriptionBlock) == 0) {
                $this->createWebBlock($webpage, 'family-1');

                $this->deleteWebBlocksByCode($webpage, 'family-2');
                $this->deleteWebBlocksByCode($webpage, 'family-2-extra-description');
            }
        } else {
            $countFamilyDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'family-2');
            if (count($countFamilyDescriptionBlock) == 0) {
                $this->deleteWebBlocksByCode($webpage, 'family-1');

                $this->createWebBlock($webpage, 'family-2');
                $this->createWebBlock($webpage, 'family-2-extra-description');
            }
        }

        $webpage->refresh();

        $this->setFamilyWebBlockOnTop($webpage, $familyDescriptionBlock);
    }

    public function setFamilyWebBlockOnTop(Webpage $webpage, $familyDescriptionBlock = 'family-1'): void
    {
        $familyWebBlock = $this->getWebpageBlocksByType($webpage, $familyDescriptionBlock)->first()->model_has_web_blocks_id;
        $familyExtraDesc = null;

        if ($familyDescriptionBlock == 'family-2') {
            $familyExtraDesc = $this->getWebpageBlocksByType($webpage, 'family-2-extra-description')->first()->model_has_web_blocks_id;
        }

        $website = $webpage->website;
        $liveProductsSnapshot = $website->liveProductsSnapshot;
        $unpublishedProductsSnapshot = $website->unpublishedProductsSnapshot;
        
        $usedWebBlockTemplateCodes = data_get($liveProductsSnapshot?->layout, 'code', data_get($unpublishedProductsSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes())));

        $productList = $this->getWebpageBlocksByType($webpage, $usedWebBlockTemplateCodes)->first()->model_has_web_blocks_id;

        $trendsWebBlock     = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1')->first()->model_has_web_blocks_id;
        $lastSeenWebBlock   = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1')->first()->model_has_web_blocks_id;
        $lastBoughtWebBlock = $this->getWebpageBlocksByType($webpage, 'recommendation-customer-recently-bought-1')->first()->model_has_web_blocks_id;

        $webBlocks = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $count = $webpage->webBlocks()->count();

        $trendsWebBlockPosition     = $count + 101;
        $lastBoughtWebBlockPosition = $count + 102;
        $lastSeenWebBlockPosition   = $count + 103;

        $runningPosition = 4;
        foreach ($webBlocks as $key => $position) {
            if ($key == $familyWebBlock) {
                $webBlocks[$key] = 1;
            } elseif ($key == $productList) {
                $webBlocks[$key] = 2;
            } elseif ($key == $familyExtraDesc) {
                $webBlocks[$key] = 3;
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
