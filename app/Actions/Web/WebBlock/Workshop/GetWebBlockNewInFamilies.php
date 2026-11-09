<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Traits\WithNewInFamiliesQuery;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockNewInFamilies
{
    use AsObject;
    use WithNewInFamiliesQuery;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $fieldValue = $webpage->website->published_layout['family']['data']['fieldValue'] ?? [];

        data_set($fieldValue, 'show_overview_button', false);
        data_set($fieldValue, 'families', WebBlockFamiliesResource::collection($this->getNewInFamilies($webpage)->get())->resolve());

        data_set($webBlock, 'web_block.layout.data.permissions', ['hidden']);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $fieldValue);
        data_set($webBlock, 'uses_website_layout', true);

        return $webBlock;
    }
}
