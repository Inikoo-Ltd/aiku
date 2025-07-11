<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 12:24:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('quantity_required', 16, 6)->default(0)->change();
            $table->decimal('quantity_picked', 16, 6)->nullable()->change();
            $table->decimal('quantity_packed', 16, 6)->nullable()->change();
            $table->decimal('quantity_dispatched', 16, 6)->nullable()->change();
            $table->decimal('quantity_not_picked', 16, 6)->nullable()->change();


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('quantity_required', 16, 3)->default(0)->change();
            $table->decimal('quantity_picked', 16, 3)->nullable()->change();
            $table->decimal('quantity_packed', 16, 3)->nullable()->change();
            $table->decimal('quantity_dispatched', 16, 3)->nullable()->change();
            $table->decimal('quantity_not_picked', 16, 3)->nullable()->change();
        });
    }
};
