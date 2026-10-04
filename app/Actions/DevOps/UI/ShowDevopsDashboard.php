<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\DevOps\Server\StoreServerLiveMetric;
use App\Actions\OrgAction;
use App\Models\DevOps\CiRun;
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
                        'icon'  => ['fal', 'fa-tools'],
                        'title' => $title,
                    ],
                ],
                'servers'          => $servers = $this->getServerSummaries(),
                'ciRuns'           => $this->getCiRuns(),
                'liveReadings'     => $servers->mapWithKeys(fn (object $server) => [$server->slug => StoreServerLiveMetric::recentReadings($server->slug)]),

            ]
        );
    }

    /** @return Collection<int, object{slug: string, name: string, recorded_at: string|null, cpu_percent: float|null, memory_percent: float|null, swap_percent: float|null, disk_percent: float|null, load_1: float|null, iowait_percent: float|null, inode_percent: float|null, net_rx_mbps: float|null, net_tx_mbps: float|null, disk_read_mbps: float|null, disk_write_mbps: float|null, processes: int|null, tcp_connections: int|null, cpu_cores: int|null, memory_total_mb: int|null, swap_total_mb: int|null, disks: string|null, top_processes: string|null, cpu_24h_max: float|null, memory_24h_max: float|null, group: string, role: string|null}> */
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
                'latest.top_processes',
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

    public const string DEPLOY_WORKFLOW = 'Deploy Aiku';

    public const string TESTS_WORKFLOW = 'Backend Tests';

    /** @var array<string, string> */
    public const array DEPLOY_HOST_NAMES = ['aiku' => 'boro', 'aiku_litio' => 'litio'];

    /** @return array{deploy: array<string, mixed>|null, tests: array<string, mixed>|null, recent_deploys: array<int, array<string, mixed>>, recent_tests: array<int, array<string, mixed>>, usual_deploy_seconds: int|null, usual_tests_seconds: int|null} */
    public function getCiRuns(): array
    {
        $recent = fn (string $workflow) => CiRun::where('workflow', $workflow)->orderByDesc('github_run_id')->limit(6)->get();

        $deploys = $recent(self::DEPLOY_WORKFLOW);
        $tests   = CiRun::where('workflow', self::TESTS_WORKFLOW)->where('branch', 'main')->orderByDesc('github_run_id')->limit(6)->get();

        return [
            'deploy'               => $deploys->first() ? $this->ciRunDetail($deploys->first()) : null,
            'tests'                => $tests->first() ? $this->ciRunDetail($tests->first()) : null,
            'recent_deploys'       => $deploys->skip(1)->map(fn (CiRun $ciRun) => $this->ciRunSummary($ciRun))->values()->all(),
            'recent_tests'         => $tests->skip(1)->map(fn (CiRun $ciRun) => $this->ciRunSummary($ciRun))->values()->all(),
            'usual_deploy_seconds' => $this->usualSeconds(self::DEPLOY_WORKFLOW),
            'usual_tests_seconds'  => $this->usualSeconds(self::TESTS_WORKFLOW, 'main'),
        ];
    }

    public function usualSeconds(string $workflow, ?string $branch = null): ?int
    {
        $seconds = CiRun::where('workflow', $workflow)->where('conclusion', 'success')
            ->when($branch, fn ($query) => $query->where('branch', $branch))
            ->whereNotNull('started_at')->whereNotNull('completed_at')
            ->orderByDesc('github_run_id')->limit(10)
            ->selectRaw('extract(epoch from completed_at - started_at) as seconds')->pluck('seconds')
            ->sort()->values();

        return $seconds->isEmpty() ? null : (int) $seconds[intdiv($seconds->count(), 2)];
    }

    /** @return array<string, mixed> */
    public function ciRunSummary(CiRun $ciRun): array
    {
        return [
            'github_run_id' => $ciRun->github_run_id,
            'workflow'      => $ciRun->workflow,
            'branch'        => $ciRun->branch,
            'head_sha'      => $ciRun->head_sha ? substr($ciRun->head_sha, 0, 10) : null,
            'head_message'  => $ciRun->head_message ? strtok($ciRun->head_message, "\n") : null,
            'actor'         => $ciRun->actor,
            'status'        => $ciRun->status,
            'conclusion'    => $ciRun->conclusion,
            'html_url'      => $ciRun->html_url,
            'started_at'    => $ciRun->started_at?->toIso8601String(),
            'completed_at'  => $ciRun->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function ciRunDetail(CiRun $ciRun): array
    {
        $tasks = [];
        $total = null;
        foreach ($ciRun->deploy_tasks as $event) {
            $task          = $tasks[$event['task']] ?? ['task' => $event['task'], 'state' => 'start', 'started_at' => $event['at'], 'finished_at' => null, 'hosts' => [], 'running' => 0, 'failed' => false];
            $host          = self::DEPLOY_HOST_NAMES[$event['host'] ?? ''] ?? ($event['host'] ?? null);
            $task['hosts'] = collect([...$task['hosts'], $host])->filter()->unique()->sort()->values()->all();
            if ($event['state'] === 'start') {
                $task['running']++;
            } else {
                $task['running']     = max(0, $task['running'] - 1);
                $task['failed']      = $task['failed'] || $event['state'] === 'failed';
                $task['finished_at'] = $event['at'];
            }
            $task['state']         = $task['failed'] ? 'failed' : ($task['running'] > 0 ? 'start' : 'done');
            $tasks[$event['task']] = $task;
            $total                 = $event['total'] ?? $total;
        }
        $isFinished = $ciRun->status === 'completed';
        $tasks      = array_map(fn (array $task) => [...array_diff_key($task, ['running' => true, 'failed' => true]), 'state' => $isFinished && $task['state'] === 'start' ? 'failed' : $task['state']], $tasks);

        $jobs = collect($ciRun->jobs)->sortBy('started_at')->values()->all();

        return [
            ...$this->ciRunSummary($ciRun),
            'jobs'         => $jobs,
            'deploy_tasks' => array_values($tasks),
            'deploy_total' => $total,
            'deploy_done'  => count(array_filter($tasks, fn (array $task) => $task['state'] === 'done')),
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
