<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 20:07:14 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('may_contain')->default(false)->nullable();
            $table->decimal('ration')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('may_contain');
            $table->dropColumn('ration');
        });
    }
};
