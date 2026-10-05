<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 03:07:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\Server;

use App\Models\DevOps\ServerMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AggregateServerMetrics
{
    use AsAction;

    public string $commandSignature = 'server-metrics:aggregate {--hours=3 : Recent hours to (re)aggregate} {--prune-days=90 : Days of minute samples to keep}';

    public function handle(int $hours = 3, int $pruneDays = 90): int
    {
        DB::statement(
            "insert into server_metric_hours (server_id, hour, samples, cpu_avg, cpu_max, memory_avg, memory_max, swap_max, disk_max, load_1_max,
                iowait_avg, iowait_max, net_rx_avg, net_rx_max, net_tx_avg, net_tx_max, disk_read_avg, disk_read_max, disk_write_avg, disk_write_max,
                processes_max, tcp_connections_max, inode_max)
            select server_id, date_trunc('hour', recorded_at), count(*), avg(cpu_percent), max(cpu_percent),
                avg(memory_percent), max(memory_percent), max(swap_percent), max(disk_percent), max(load_1),
                avg(iowait_percent), max(iowait_percent), avg(net_rx_mbps), max(net_rx_mbps), avg(net_tx_mbps), max(net_tx_mbps),
                avg(disk_read_mbps), max(disk_read_mbps), avg(disk_write_mbps), max(disk_write_mbps), max(processes), max(tcp_connections), max(inode_percent)
            from server_metrics
            where recorded_at >= date_trunc('hour', now()) - make_interval(hours => ?)
            group by 1, 2
            on conflict (server_id, hour) do update set samples = excluded.samples, cpu_avg = excluded.cpu_avg,
                cpu_max = excluded.cpu_max, memory_avg = excluded.memory_avg, memory_max = excluded.memory_max,
                swap_max = excluded.swap_max, disk_max = excluded.disk_max, load_1_max = excluded.load_1_max,
                iowait_avg = excluded.iowait_avg, iowait_max = excluded.iowait_max, net_rx_avg = excluded.net_rx_avg, net_rx_max = excluded.net_rx_max,
                net_tx_avg = excluded.net_tx_avg, net_tx_max = excluded.net_tx_max, disk_read_avg = excluded.disk_read_avg, disk_read_max = excluded.disk_read_max,
                disk_write_avg = excluded.disk_write_avg, disk_write_max = excluded.disk_write_max, processes_max = excluded.processes_max,
                tcp_connections_max = excluded.tcp_connections_max, inode_max = excluded.inode_max",
            [$hours]
        );

        return ServerMetric::where('recorded_at', '<', now()->subDays($pruneDays))->delete();
    }

    public function asCommand(Command $command): int
    {
        $pruned = $this->handle((int) $command->option('hours'), (int) $command->option('prune-days'));
        $command->info("Aggregated; pruned $pruned old samples.");

        return 0;
    }
}
