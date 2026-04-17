<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 22:31:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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

        return data_get($liveWebBlockSnapshot?->layout, 'code', data_get($unpublishedWebBlockSnapshot?->layout, 'code', array_first(WebBlockTemplateEnum::FAMILY_OVERVIEW->templateCodes())));

    }
}
