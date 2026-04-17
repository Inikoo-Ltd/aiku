<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Apr 2026 23:48:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'organisation_id', 'fetched_at', 'last_fetched_at']);
        });

        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropColumn(['fetched_at', 'last_fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->nullable();
            $table->unsignedSmallInteger('organisation_id')->nullable();
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampTz('last_fetched_at')->nullable();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('set null');
        });

        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampTz('last_fetched_at')->nullable();
        });
    }
};
