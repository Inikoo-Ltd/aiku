<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Sept 2025 17:24:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('effective_total', 16)->default(0)->comment('effective total to pay');
        });
    }


    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('effective_total');
        });
    }
};
