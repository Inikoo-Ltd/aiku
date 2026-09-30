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
     * The GitHub pull request that fixes the ticket. Only the link is kept: its title, state and
     * author are read from GitHub when the ticket is opened, so they never go stale.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('pull_request_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('pull_request_url');
        });
    }
};
