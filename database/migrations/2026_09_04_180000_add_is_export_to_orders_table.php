<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_export')->default(false)->index()->comment('delivery leaves the customs territory of the organisation');
        });

        $euCodes = "'".implode("','", \App\Actions\Helpers\Country\UI\IsEuropeanUnion::getEUCountryCodes())."'";

        DB::statement("
            update orders o set is_export = true
            from organisations org
            join countries oc on oc.id = org.country_id,
            addresses a
            left join countries dc on dc.id = a.country_id
            where org.id = o.organisation_id
              and a.id = o.delivery_address_id
              and o.handing_type = 'shipping'
              and case
                    when oc.code = 'GB' then dc.code is distinct from 'GB'
                    else (dc.code = 'ES' and a.postal_code ~ '^(35|38|51|52)') or dc.code not in ($euCodes)
                  end
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_export');
        });
    }
};
