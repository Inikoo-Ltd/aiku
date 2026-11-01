<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->unsignedInteger('varnish_hits')->nullable();
            $table->unsignedInteger('varnish_misses')->nullable();
            $table->decimal('varnish_memory_mb', 10, 2)->nullable();
        });

        Schema::table('server_metric_hours', function (Blueprint $table) {
            $table->unsignedBigInteger('varnish_hits')->nullable();
            $table->unsignedBigInteger('varnish_misses')->nullable();
            $table->decimal('varnish_memory_avg', 10, 2)->nullable();
            $table->decimal('varnish_memory_max', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->dropColumn(['varnish_hits', 'varnish_misses', 'varnish_memory_mb']);
        });

        Schema::table('server_metric_hours', function (Blueprint $table) {
            $table->dropColumn(['varnish_hits', 'varnish_misses', 'varnish_memory_avg', 'varnish_memory_max']);
        });
    }
};
