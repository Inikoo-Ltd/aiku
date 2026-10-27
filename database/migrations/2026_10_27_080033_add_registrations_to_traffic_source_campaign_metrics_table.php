<?php

/*
 * Author: Andi Ferdiawan <andiferdiawan22@gmail.com>
 * Created: Tue, 27 Oct 2026
 * Copyright (c) 2026, Andi Ferdiawan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_source_campaign_metrics', function (Blueprint $table) {
            $table->decimal('registrations', 16, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaign_metrics', function (Blueprint $table) {
            $table->dropColumn('registrations');
        });
    }
};
