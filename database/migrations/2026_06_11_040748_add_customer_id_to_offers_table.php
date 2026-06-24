<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 12:07:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable()->index()->comment('exclusive customer offer');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
};
