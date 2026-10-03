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
                'publicSiteVisits' => $this->getPublicSiteVisits(),
                'servers'          => $servers = $this->getServerSummaries(),
                'liveReadings'     => $servers->mapWithKeys(fn (object $server) => [$server->slug => StoreServerLiveMetric::recentReadings($server->slug)]),

            ]
        );
    }

    /** @return Collection<int, object{slug: string, name: string, recorded_at: string|null, cpu_percent: float|null, memory_percent: float|null, swap_percent: float|null, disk_percent: float|null, load_1: float|null, iowait_percent: float|null, inode_percent: float|null, net_rx_mbps: float|null, net_tx_mbps: float|null, disk_read_mbps: float|null, disk_write_mbps: float|null, processes: int|null, tcp_connections: int|null, cpu_cores: int|null, memory_total_mb: int|null, disks: string|null, cpu_24h_max: float|null, memory_24h_max: float|null}> */
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
                'latest.cpu_cores',
                'latest.memory_total_mb',
                'latest.disks',
                'day.cpu_24h_max',
                'day.memory_24h_max'
            )
            ->orderBy('servers.name')->get();
    }

    /** @return array{daily: array<int, object>, visitors: int, views: int, top_referrer: string|null} */
    public function getPublicSiteVisits(): array
    {
        $visits = fn (int $days) => DB::table('aiku_public_visits')->where('is_bot', false)
            ->where('created_at', '>', now()->subDays($days))
            ->where('path', 'not like', '/~search/%');

        $lastWeek = $visits(7)->selectRaw('count(*) as views, count(distinct visitor_hash) as visitors')->first();

        return [
            'daily' => $visits(14)
                ->selectRaw('created_at::date as day, count(*) as views, count(distinct visitor_hash) as visitors')
                ->groupBy('day')->orderBy('day')->get()->all(),
            'visitors'     => (int) $lastWeek->visitors,
            'views'        => (int) $lastWeek->views,
            'top_referrer' => $visits(7)->whereNotNull('referrer')
                ->selectRaw('referrer, count(distinct visitor_hash) as visitors')
                ->groupBy('referrer')->orderByDesc(DB::raw('count(distinct visitor_hash)'))->value('referrer'),
        ];
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
