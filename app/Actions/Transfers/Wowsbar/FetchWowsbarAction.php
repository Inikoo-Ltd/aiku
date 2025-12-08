<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Apr 2024 15:11:45 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Wowsbar;

use App\Actions\Transfers\FetchAction;

class FetchWowsbarAction extends FetchAction
{
    use WithWowsbarOrganisationsArgument;
}
