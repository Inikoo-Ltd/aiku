<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 03:07:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->timestampTz('recorded_at');
            $table->decimal('cpu_percent', 5, 2);
            $table->decimal('memory_percent', 5, 2);
            $table->decimal('swap_percent', 5, 2)->nullable();
            $table->decimal('disk_percent', 5, 2);
            $table->decimal('load_1', 8, 2)->nullable();
            $table->decimal('iowait_percent', 5, 2)->nullable();
            $table->decimal('net_rx_mbps', 10, 2)->nullable();
            $table->decimal('net_tx_mbps', 10, 2)->nullable();
            $table->decimal('disk_read_mbps', 10, 2)->nullable();
            $table->decimal('disk_write_mbps', 10, 2)->nullable();
            $table->unsignedInteger('processes')->nullable();
            $table->unsignedInteger('tcp_connections')->nullable();
            $table->decimal('inode_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedBigInteger('memory_total_mb')->nullable();
            $table->jsonb('disks')->nullable();
            $table->index(['server_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        Schema::create('server_metric_hours', function (Blueprint $table) {
            $table->unsignedSmallInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->timestampTz('hour');
            $table->unsignedSmallInteger('samples');
            $table->decimal('cpu_avg', 5, 2);
            $table->decimal('cpu_max', 5, 2);
            $table->decimal('memory_avg', 5, 2);
            $table->decimal('memory_max', 5, 2);
            $table->decimal('swap_max', 5, 2)->nullable();
            $table->decimal('disk_max', 5, 2);
            $table->decimal('load_1_max', 8, 2)->nullable();
            $table->decimal('iowait_avg', 5, 2)->nullable();
            $table->decimal('iowait_max', 5, 2)->nullable();
            $table->decimal('net_rx_avg', 10, 2)->nullable();
            $table->decimal('net_rx_max', 10, 2)->nullable();
            $table->decimal('net_tx_avg', 10, 2)->nullable();
            $table->decimal('net_tx_max', 10, 2)->nullable();
            $table->decimal('disk_read_avg', 10, 2)->nullable();
            $table->decimal('disk_read_max', 10, 2)->nullable();
            $table->decimal('disk_write_avg', 10, 2)->nullable();
            $table->decimal('disk_write_max', 10, 2)->nullable();
            $table->unsignedInteger('processes_max')->nullable();
            $table->unsignedInteger('tcp_connections_max')->nullable();
            $table->decimal('inode_max', 5, 2)->nullable();
            $table->primary(['server_id', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metric_hours');
        Schema::dropIfExists('server_metrics');
    }
};
