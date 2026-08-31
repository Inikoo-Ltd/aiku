<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_partner_stats', function (Blueprint $table) {
            $table->decimal('open_shopping_list_items_value', 16, 2)->default(0)
                ->comment('value of open items in the partner organisation currency');
        });
    }

    public function down(): void
    {
        Schema::table('org_partner_stats', function (Blueprint $table) {
            $table->dropColumn('open_shopping_list_items_value');
        });
    }
};
