<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jun 2026 21:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('offer_voucher_id')->nullable()->index();
            $table->foreign('offer_voucher_id')->references('id')->on('offers')->nullOnDelete();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->string('voucher', 32)->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['offer_voucher_id']);
            $table->dropColumn('offer_voucher_id');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('voucher');
        });
    }
};
