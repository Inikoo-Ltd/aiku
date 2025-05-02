<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 12:07:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('colour')->nullable();
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->string('colour')->nullable();
        });
        Schema::table('invoice_categories', function (Blueprint $table) {
            $table->string('colour')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('colour');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('colour');
        });
        Schema::table('invoice_categories', function (Blueprint $table) {
            $table->dropColumn('colour');
        });
    }
};
