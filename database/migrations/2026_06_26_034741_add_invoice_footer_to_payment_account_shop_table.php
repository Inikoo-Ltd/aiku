<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jun 2026 11:52:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->text('invoice_footer')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->dropColumn('invoice_footer');
        });
    }
};
