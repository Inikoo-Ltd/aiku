<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\WebUser\DeleteWebUser;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationArgument;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;

class ForceDeleteCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithOrganisationArgument;





    public function handle(Customer $customer): void
    {

        foreach ($customer->webUsers as $webUser) {
            DeleteWebUser::run($webUser, true);
        }
        DB::table('customer_comms')->where('customer_id', $customer->id)->delete();
        DB::table('customer_stats')->where('customer_id', $customer->id)->delete();
        DB::table('audits')->where('customer_id', $customer->id)->delete();
        DB::table('audits')->where('auditable_type', 'Customer')->where('auditable_id', $customer->id)->delete();
        DB::table('universal_searches')->where('customer_id', $customer->id)->delete();
        DB::table('universal_searches')->where('model_type', 'Customer')->where('model_id', $customer->id)->delete();

        $customer->forceDelete();
    }



}
