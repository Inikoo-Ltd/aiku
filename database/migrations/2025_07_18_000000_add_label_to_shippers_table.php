<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Jul 2025 18:11:12 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->string('trade_as')->nullable()->comment('to be shown in retina UI');
        });
    }


    public function down(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->dropColumn('trade_as');
        });
    }
};
