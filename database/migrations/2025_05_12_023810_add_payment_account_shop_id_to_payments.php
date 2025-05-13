<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 May 2025 10:39:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('payment_account_shop_id')->nullable()->after('payment_account_id');
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop');
            $table->nullableMorphs('api_point');
        });
    }


    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_account_shop_id']);
            $table->dropColumn('payment_account_shop_id');
            $table->dropMorphs('api_point');
        });
    }
};
