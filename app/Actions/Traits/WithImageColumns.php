<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 20:28:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

trait WithImageColumns
{
    /**
     * Standard list of image column keys used across models.
     *
     * @return array<int, string>
     */
    public function imageColumns(): array
    {
        return [
            'image_id',
            'front_image_id',
            '34_image_id',
            'right_image_id',
            'back_image_id',
            'bottom_image_id',
            'size_comparison_image_id',
            'lifestyle_image_id',
            'top_image_id',
        ];
    }
}
