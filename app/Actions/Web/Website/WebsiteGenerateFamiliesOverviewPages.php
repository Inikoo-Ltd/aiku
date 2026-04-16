<?php

namespace App\Actions\Web\Website;

use App\Actions\Maintenance\Web\WithRepairWebpages;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteGenerateFamiliesOverviewPages
{
    use AsAction;
    use WithRepairWebpages;

    public int $jobTries = 1;
    public string $jobQueue = 'low-priority';

    public function handle(Website $website)
    {
        $generateFamiliesOverview = data_get($website->settings, 'catalogue_pages.description_has_overview', false);
        $shop = $website->shop;
        
        foreach ($shop->departments() as $department) {

            $hasFamiliesOverview = $department->webpages()->where('layout_style', 'families-overview')->exists();

            if ($generateFamiliesOverview && !$hasFamiliesOverview) {

                $webpageData = [
                    'title'         => $department->name,
                    'code'          => $department->code . '-overview',
                    'url'           => strtolower($department->code) . '-overview',
                    'sub_type'      => WebpageSubTypeEnum::DEPARTMENT,
                    'type'          => WebpageTypeEnum::CATALOGUE,
                    'model_type'    => class_basename($department),
                    'model_id'      => $department->id,
                    'layout_style'  => 'families-overview'
                ];
    
                $overviewPage = StoreWebpage::make()->action($department->shop->website, $webpageData);
                PublishWebpage::make()->action($overviewPage, [
                    'comment'   => 'Generate Overview Page'
                ]);

            } elseif (!$generateFamiliesOverview && $hasFamiliesOverview) {
                foreach ($department->webpages()->where('layout_style', 'families-overview')->get() as $webpage) {
                    DeleteWebpage::make()->action($webpage);
                }
            }

            if ($mainPage = $department->webpages()->where('layout_style', 'main_page')->first()) {
                $department->updateQuietly([
                    'webpage_id'    => $mainPage->id,
                    'url'           => $mainPage->url,
                ]);

                $this->normalizeWebBlockByType($mainPage, WebBlockTemplateEnum::FAMILIES->templateCodes(), WebBlockTemplateEnum::FAMILIES);
                $this->reorderDepartmentPageBlocks($mainPage);
            }

        }
    }
}
