<?php

/*
 * author Louis Perez
 * created on 15-04-2026-10h-04m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Website\Layouts;

use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchUsedFamilyDescriptionWebBlock
{
    use AsAction;

    public function handle(Website $website): array
    {
        $liveWebBlockSnapshot = $website->liveFamilyDescriptionSnapshot;
        $unpublishedWebBlockSnapshot = $website->unpublishedFamilyDescriptionSnapshot;

        $usedWebBlockTemplateCodes = $liveWebBlockSnapshot?->layout ? array_keys($liveWebBlockSnapshot?->layout) : ($unpublishedWebBlockSnapshot?->layout ? array_keys($unpublishedWebBlockSnapshot?->layout) : [array_first(WebBlockTemplateEnum::FAMILY_DESCRIPTION->templateCodes())]);

        return $usedWebBlockTemplateCodes;
    }
}
