<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $mergedInto = [
            'billables'    => 'accounting',
            'catalogue'    => 'products',
            'masters'      => 'products',
            'goods'        => 'products',
            'comms'        => 'marketing',
            'discounts'    => 'marketing',
            'reviews'      => 'marketing',
            'iris'         => 'websites',
            'web'          => 'websites',
            'retina'       => 'websites',
            'goods_in'     => 'procurement',
            'supply_chain' => 'procurement',
            'transfers'    => 'procurement',
            'sysadmin'     => 'system',
            'devops'       => 'system',
            'maintenance'  => 'system',
            'search'       => 'system',
            'integrations' => 'dropshipping',
        ];

        foreach ($mergedInto as $retired => $module) {
            DB::table('tickets')->where('module', $retired)->update(['module' => $module]);
        }
    }

    public function down(): void
    {
    }
};
