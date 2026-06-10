<?php

/*
 * author Louis Perez
 * created on 09-06-2026-16h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Website\Layouts;

use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchUsedDepartmentDescriptionWebBlock
{
    use AsAction;

    public function handle(Website $website): array
    {
        $liveWebBlockSnapshot = $website->liveDepartmentDescriptionSnapshot;
        $unpublishedWebBlockSnapshot = $website->unpublishedDepartmentDescriptionSnapshot;

        $usedWebBlockTemplateCodes = $liveWebBlockSnapshot?->layout ? array_keys($liveWebBlockSnapshot?->layout) : ($unpublishedWebBlockSnapshot?->layout ? array_keys($unpublishedWebBlockSnapshot?->layout) : [array_first(WebBlockTemplateEnum::DEPARTMENT_DESCRIPTION->templateCodes())]);

        return $usedWebBlockTemplateCodes;
    }
}
