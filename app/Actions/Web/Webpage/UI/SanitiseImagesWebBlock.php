<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 11 May 2026 14:47:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class SanitiseImagesWebBlock
{
    use AsObject;

    public function handle(array $webBlockData): array
    {
        $structure = Arr::get($webBlockData, 'web_block.layout.data.fieldValue');

        if (isset($structure['value']['images']) && is_array($structure['value']['images'])) {
            foreach ($structure['value']['images'] as &$image) {
                unset($image['link_data']['workshop']);
            }
        }

        return [
            'type'      => 'images',
            'structure' => $structure
        ];
    }

}
