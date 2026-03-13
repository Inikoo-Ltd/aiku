<?php

/*
 * author Louis Perez
 * created on 11-03-2026-10h-31m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Website\Layouts;

use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchUsedSubDepartmentsWebBlock
{
    use AsAction;

    public function handle(Website $website): string
    {
        $liveWebBlockSnapshot = $website->liveSubDepartmentSnapshot;
        $unpublishedWebBlockSnapshot = $website->unpublishedSubDepartmentSnapshot;

        $usedWebBlockTemplateCodes = data_get($liveWebBlockSnapshot?->layout, 'code', data_get($unpublishedWebBlockSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::SUB_DEPARTMENTS->templateCodes())));

        return $usedWebBlockTemplateCodes;
    }
}
