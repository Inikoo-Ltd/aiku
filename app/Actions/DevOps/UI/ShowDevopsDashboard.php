<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\DevOps\Server\StoreServerLiveMetric;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDevopsDashboard extends OrgAction
{
    use WithInertia;

    /** @var array<string, array{0: string, 1: string}> */
    public const array SERVER_ROLES = [
        'boro'  => ['Production', 'Primary'],
        'litio' => ['Production', 'Secondary'],
        'helio' => ['Ops support', 'CI & replica'],
        'neon'  => ['Ops support', 'Staging'],
    ];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title         = __('Devops Dashboard');


        return Inertia::render(
            'Devops/Dashboard',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'           => $title,
                'pageHead'        => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-server'],
                        'title' => $title,
                    ],
                ],
                'servers'          => $servers = $this->getServerSummaries(),
                'liveReadings'     => $servers->mapWithKeys(fn (object $server) => [$server->slug => StoreServerLiveMetric::recentReadings($server->slug)]),

            ]
        );
    }

    /** @return Collection<int, object{slug: string, name: string, recorded_at: string|null, cpu_percent: float|null, memory_percent: float|null, swap_percent: float|null, disk_percent: float|null, load_1: float|null, iowait_percent: float|null, inode_percent: float|null, net_rx_mbps: float|null, net_tx_mbps: float|null, disk_read_mbps: float|null, disk_write_mbps: float|null, processes: int|null, tcp_connections: int|null, cpu_cores: int|null, memory_total_mb: int|null, swap_total_mb: int|null, disks: string|null, cpu_24h_max: float|null, memory_24h_max: float|null, group: string, role: string|null}> */
    public function getServerSummaries(): Collection
    {
        return DB::table('servers')->where('active', true)
            ->leftJoinLateral(
                DB::table('server_metrics')->whereColumn('server_metrics.server_id', 'servers.id')->orderByDesc('recorded_at')->limit(1),
                'latest'
            )
            ->leftJoinLateral(
                DB::table('server_metric_hours')->whereColumn('server_metric_hours.server_id', 'servers.id')->where('hour', '>', now()->subDay())
                    ->selectRaw('max(cpu_max) as cpu_24h_max, max(memory_max) as memory_24h_max'),
                'day'
            )
            ->select(
                'servers.slug',
                'servers.name',
                'latest.recorded_at',
                'latest.cpu_percent',
                'latest.memory_percent',
                'latest.swap_percent',
                'latest.disk_percent',
                'latest.load_1',
                'latest.iowait_percent',
                'latest.inode_percent',
                'latest.net_rx_mbps',
                'latest.net_tx_mbps',
                'latest.disk_read_mbps',
                'latest.disk_write_mbps',
                'latest.processes',
                'latest.tcp_connections',
                'latest.cpu_cores',
                'latest.memory_total_mb',
                'latest.swap_total_mb',
                'latest.disks',
                'day.cpu_24h_max',
                'day.memory_24h_max'
            )
            ->get()
            ->map(function (object $server) {
                $server->group = self::SERVER_ROLES[$server->slug][0] ?? __('Other');
                $server->role  = self::SERVER_ROLES[$server->slug][1] ?? null;

                return $server;
            })
            ->sortBy(fn (object $server) => array_search($server->slug, array_keys(self::SERVER_ROLES)) === false ? PHP_INT_MAX : array_search($server->slug, array_keys(self::SERVER_ROLES)))
            ->values();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comment-alt',
                        'route' => [
                            'name'       => 'grp.devops.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Devops Dashboard'),
                    ],
                ],
            ]
        );
    }
}
