<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sep 2026 09:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('mit_saved_cards', function (Blueprint $table) {
            $table->dropForeign(['payment_account_shop_id']);
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop');
        });
    }

    public function down(): void
    {
        Schema::table('mit_saved_cards', function (Blueprint $table) {
            $table->dropForeign(['payment_account_shop_id']);
            $table->foreign('payment_account_shop_id')->references('id')->on('customers');
        });
    }
};
