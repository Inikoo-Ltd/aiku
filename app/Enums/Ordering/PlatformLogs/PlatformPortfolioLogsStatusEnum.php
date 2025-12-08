<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:24:56 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\PlatformLogs;

use App\Enums\EnumHelperTrait;

enum PlatformPortfolioLogsStatusEnum: string
{
    use EnumHelperTrait;

    case OK = 'ok';
    case PROCESSING = 'processing';
    case FAIL = 'fail';

    public function labels(): array
    {
        return [
            'ok' => 'Ok',
            'processing' => 'Processing',
            'fail' => 'Fail',
        ];
    }
}
