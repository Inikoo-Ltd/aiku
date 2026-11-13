<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['aw' => 'kristy', 'es' => 'quentin', 'sk' => 'juraj'] as $organisationSlug => $username) {
            $organisation = DB::table('organisations')->where('slug', $organisationSlug)->first(['id', 'group_id', 'settings']);
            if (!$organisation) {
                continue;
            }

            $settings = json_decode($organisation->settings ?? '{}', true) ?: [];
            if (data_get($settings, 'procurement.delivery_buyer_id')) {
                continue;
            }

            $userId = DB::table('users')->where('group_id', $organisation->group_id)->where('username', $username)->value('id');
            if (!$userId) {
                continue;
            }

            data_set($settings, 'procurement.delivery_buyer_id', $userId);
            DB::table('organisations')->where('id', $organisation->id)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
    }
};
