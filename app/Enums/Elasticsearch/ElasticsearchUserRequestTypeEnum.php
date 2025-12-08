<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 02:24:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Elasticsearch;

use App\Enums\EnumHelperTrait;

enum ElasticsearchUserRequestTypeEnum: string
{
    use EnumHelperTrait;

    case VISIT = 'visit';
    case ACTION = 'action';
    case FAIL_LOGIN = 'fail_login';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
}
