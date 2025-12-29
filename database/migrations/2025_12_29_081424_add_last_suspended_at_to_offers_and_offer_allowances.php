<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Dec 2025 16:29:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dateTimeTz('last_suspended_at')->nullable();
        });

        Schema::table('offer_allowances', function (Blueprint $table) {
            $table->dateTimeTz('last_suspended_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('last_suspended_at');
        });

        Schema::table('offer_allowances', function (Blueprint $table) {
            $table->dropColumn('last_suspended_at');
        });
    }
};
