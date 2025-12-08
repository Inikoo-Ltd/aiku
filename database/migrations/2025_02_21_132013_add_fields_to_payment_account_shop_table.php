<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Feb 2025 21:21:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->dateTimeTz('activated_at')->nullable();
            $table->dateTimeTz('last_activated_at')->nullable();
            $table->dateTimeTz('inactive_at')->nullable();
            $table->boolean('show_in_checkout')->default(false);
            $table->integer('checkout_display_position')->default(0)->comment('for the order in which will be show in checkout UI');
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->dropColumn('source_id');
            $table->dropColumn('checkout_display_position');
            $table->dropColumn('show_in_checkout');
            $table->dropColumn('inactive_at');
            $table->dropColumn('active_at');
            $table->dropColumn('last_activated_at');
        });
    }
};
