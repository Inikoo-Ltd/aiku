<?php

/*
 * author Louis Perez
 * created on 15-04-2026-10h-05m
 * github: https://github.com/louis-perez
 * copyright 2026
*/


namespace App\Actions\Web\Website\Layouts;

use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchUsedFamiliesOverviewWebBlock
{
    use AsAction;

    public function handle(Website $website): string
    {
        $liveWebBlockSnapshot = $website->liveFamiliesOverviewSnapshot;
        $unpublishedWebBlockSnapshot = $website->unpublishedFamiliesOverviewSnapshot;

        $usedWebBlockTemplateCodes = data_get($liveWebBlockSnapshot?->layout, 'code', data_get($unpublishedWebBlockSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::FAMILY_OVERVIEW->templateCodes())));

        return $usedWebBlockTemplateCodes;
    }
}
