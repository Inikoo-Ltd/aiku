<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 14:40:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('delivery_address_id')->nullable()->index();
            $table->foreign('delivery_address_id')->references('id')->on('addresses');
            $table->unsignedSmallInteger('delivery_country_id')->index()->nullable();
            $table->foreign('delivery_country_id')->references('id')->on('countries');
        });
    }


    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['delivery_address_id']);
            $table->dropIndex(['delivery_address_id']);
            $table->dropColumn('delivery_address_id');

            $table->dropForeign(['delivery_country_id']);
            $table->dropIndex(['delivery_country_id']);
            $table->dropColumn('delivery_country_id');
        });
    }
};
