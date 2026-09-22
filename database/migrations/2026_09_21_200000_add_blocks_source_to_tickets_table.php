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
     * A ticket raised from a chat can hold that chat open until it is resolved, so an agent does not
     * close a conversation whose work is still outstanding. Off for every existing ticket, since
     * none of them were raised with that promise attached.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('blocks_source')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('blocks_source');
        });
    }
};
