<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 May 2025 13:22:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('purged_orders', function (Blueprint $table) {
            $table->renameColumn('number_transaction', 'number_transactions');
        });
    }


    public function down()
    {
        Schema::table('purged_orders', function (Blueprint $table) {
            $table->renameColumn('number_transactions', 'number_transaction');
        });
    }
};
