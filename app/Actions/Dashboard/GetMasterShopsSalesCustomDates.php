<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Oct 2025 14:01:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

class GetMasterShopsSalesCustomDates extends OrgAction
{
    public function handle(Group $group)
    {
        dd($group);
    }

    public function rules()
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ];
    }



    public function asController(ActionRequest $request)
    {
        dd($request->all());
    }


}
