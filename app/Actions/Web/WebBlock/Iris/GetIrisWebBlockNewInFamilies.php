<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithNewInFamiliesQuery;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockNewInFamilies
{
    use AsObject;
    use WithNewInFamiliesQuery;

    /**
     * The families block of the NEW IN page, drawn from the website family template like on every catalogue page.
     */
    public function handle(Webpage $webpage, array $webBlock): array
    {
        $fieldValue = $webpage->website->published_layout['family']['data']['fieldValue'] ?? [];

        data_set($fieldValue, 'show_overview_button', false);
        data_set($fieldValue, 'families', WebBlockFamiliesResource::collection($this->getNewInFamilies($webpage)->get())->resolve());

        return [
            'type'      => $webBlock['type'],
            'structure' => $fieldValue,
        ];
    }
}
