<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:24:56 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\PlatformLogs;

use App\Enums\EnumHelperTrait;

enum PlatformPortfolioLogsTypeEnum: string
{
    use EnumHelperTrait;

    case UPDATE_STOCK = 'update-stock';
    case UPLOAD = 'upload';
    case MATCH = 'match';

    public function labels(): array
    {
        return [
            'update-stock' => 'Update Stock',
            'upload' => 'Upload',
            'match' => 'Match',
        ];
    }
}
