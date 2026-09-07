<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'CustomerSalesChannel')
            ->where('abilities', '["retina"]')
            ->update(['abilities' => json_encode(['retina', 'retina:read', 'retina:write'])]);
    }

    public function down(): void
    {
    }
};
