<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

trait WithGetWebpageWebBlocks
{
    use WithFillWorkshopWebBlocks;

    public function getWebBlocks(Webpage $webpage, array $webBlocks): array
    {
        $parsedWebBlocks = [];

        foreach ($webBlocks as $key => $webBlock) {
            $parsedWebBlocks = $this->fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock, false);
        }

        return $this->refreshWebBlockTypeNames($webpage, $parsedWebBlocks);
    }

    /**
     * The snapshot layout keeps a copy of the web block type name made when the content was last saved,
     * so a renamed web block type would keep showing its old name until the page is edited again.
     */
    protected function refreshWebBlockTypeNames(Webpage $webpage, array $webBlocks): array
    {
        $codes = array_filter(Arr::pluck($webBlocks, 'type'));

        if ($codes === []) {
            return $webBlocks;
        }

        $names = WebBlockType::where('group_id', $webpage->group_id)
            ->whereIn('code', $codes)
            ->pluck('name', 'code');

        foreach ($webBlocks as $key => $webBlock) {
            $name = $names->get(Arr::get($webBlock, 'type'));
            if ($name) {
                $webBlocks[$key]['name'] = $name;
            }
        }

        return $webBlocks;
    }
}
