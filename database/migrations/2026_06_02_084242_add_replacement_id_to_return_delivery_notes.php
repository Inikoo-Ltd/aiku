<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jun 2026 19:57:44 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_id')->nullable();
            $table->foreign('replacement_id')->references('id')->on('delivery_notes');
        });
    }


    public function down(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropForeign('replacement_id');
            $table->dropColumn('replacement_id');
        });
    }
};
