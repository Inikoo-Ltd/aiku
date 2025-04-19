<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 20:39:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards;

use App\Actions\Traits\Dashboards\Settings\WithDashboardDataDisplayTypeSettings;
use App\Actions\Traits\Dashboards\Settings\WithDashboardShopStateTypeSettings;

trait WithDashboardSettings
{
    use WithDashboardShopStateTypeSettings;
    use WithDashboardDataDisplayTypeSettings;



}
