<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 03:07:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\DevOps\Server\StoreServerLiveMetric;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\DevOps\Server;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowServer extends OrgAction
{
    use WithInertia;

    public const array RANGES = ['24h', '7d', '30d', '1y', 'all'];

    private string $range = '24h';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(Server $server, ActionRequest $request): Server
    {
        $this->initialisationFromGroup(app('group'), $request);
        $this->range = in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : '24h';

        return $server;
    }

    /** @return array<int, object{t: string, cpu: float, cpu_max: float, memory: float, memory_max: float, swap: float|null, disk: float, load: float|null, iowait: float|null, iowait_max: float|null, inode: float|null, net_rx: float|null, net_tx: float|null, disk_read: float|null, disk_write: float|null, processes: int|null, tcp_connections: int|null}> */
    public function getSeries(Server $server, string $range): array
    {
        if ($range === '24h') {
            return DB::table('server_metrics')->where('server_id', $server->id)
                ->where('recorded_at', '>', now()->subDay())
                ->selectRaw("to_char(recorded_at at time zone 'UTC', 'DD HH24:MI') as t, cpu_percent as cpu, cpu_percent as cpu_max, memory_percent as memory, memory_percent as memory_max, swap_percent as swap, disk_percent as disk, load_1 as load, iowait_percent as iowait, iowait_percent as iowait_max, inode_percent as inode, net_rx_mbps as net_rx, net_tx_mbps as net_tx, disk_read_mbps as disk_read, disk_write_mbps as disk_write, processes, tcp_connections")
                ->orderBy('recorded_at')->get()->all();
        }

        [$since, $bucket, $format] = match ($range) {
            '7d'    => [now()->subDays(7), 'hour', 'MM-DD HH24:00'],
            '30d'   => [now()->subDays(30), 'hour', 'MM-DD HH24:00'],
            '1y'    => [now()->subYear(), 'day', 'YYYY-MM-DD'],
            default => [null, 'day', 'YYYY-MM-DD'],
        };

        return DB::table('server_metric_hours')->where('server_id', $server->id)
            ->when($since, fn ($query) => $query->where('hour', '>', $since))
            ->selectRaw("to_char(date_trunc('$bucket', hour at time zone 'UTC'), '$format') as t,
                sum(cpu_avg * samples) / sum(samples) as cpu, max(cpu_max) as cpu_max,
                sum(memory_avg * samples) / sum(samples) as memory, max(memory_max) as memory_max,
                max(swap_max) as swap, max(disk_max) as disk, max(load_1_max) as load,
                sum(iowait_avg * samples) / sum(samples) as iowait, max(iowait_max) as iowait_max, max(inode_max) as inode,
                sum(net_rx_avg * samples) / sum(samples) as net_rx, sum(net_tx_avg * samples) / sum(samples) as net_tx,
                sum(disk_read_avg * samples) / sum(samples) as disk_read, sum(disk_write_avg * samples) / sum(samples) as disk_write,
                max(processes_max) as processes, max(tcp_connections_max) as tcp_connections")
            ->groupBy('t')->orderBy('t')->get()->all();
    }

    public function htmlResponse(Server $server, ActionRequest $request): Response
    {
        return Inertia::render(
            'Devops/Server',
            [
                'breadcrumbs' => array_merge(
                    ShowDevopsDashboard::make()->getBreadcrumbs([]),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.devops.servers.show', 'parameters' => [$server->slug]], 'label' => $server->name]]]
                ),
                'title'    => $server->name,
                'pageHead' => [
                    'title' => $server->name,
                    'icon'  => ['icon' => ['fal', 'fa-server'], 'title' => __('Server')],
                ],
                'server' => ShowDevopsDashboard::make()->getServerSummaries()->firstWhere('slug', $server->slug),
                'liveReadings' => [$server->slug => StoreServerLiveMetric::recentReadings($server->slug)],
                'range'  => $this->range,
                'ranges' => self::RANGES,
                'series' => $this->getSeries($server, $this->range),
            ]
        );
    }
}
