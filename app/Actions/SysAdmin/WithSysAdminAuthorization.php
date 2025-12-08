<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jul 2024 18:44:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use Lorisleiva\Actions\ActionRequest;

trait WithSysAdminAuthorization
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('sysadmin.edit');

        return $request->user()->authTo('sysadmin.view');
    }
}
