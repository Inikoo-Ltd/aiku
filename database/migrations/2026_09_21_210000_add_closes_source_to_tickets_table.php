<?php

/*
 * Author: Louis Perez
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A ticket that holds its chat open can also let go of it: settling the ticket sends the
     * closing note to the customer and ends the conversation, so whoever fixed the thing does
     * not have to find an agent to say so. Off for every existing ticket, since nobody agreed
     * to have their words sent to a customer.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('closes_source')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('closes_source');
        });
    }
};
