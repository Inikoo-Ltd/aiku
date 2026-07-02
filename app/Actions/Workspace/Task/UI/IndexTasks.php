<?php

namespace App\Actions\Workspace\Task\UI;

use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\Workspace\Task;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexTasks extends GrpAction
{
    use AsAction;
    use WithInertia;

    public function handle()
    {
        return Task::with(['assigner', 'assignee'])->latest()->get();
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation(app('group'), $request);
        
        $tasks = $this->handle();
        
        return $this->htmlResponse($tasks, $request);
    }
    
    public function htmlResponse($tasks, ActionRequest $request): Response
    {
        $title = __('Tasks');
        
        return Inertia::render(
            'Workspace/Tasks/Index',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-tasks'],
                        'title' => $title,
                    ],
                ],
                'tasks' => $tasks,
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-tasks',
                        'route' => [
                            'name'       => 'grp.workspace.tasks.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Tasks'),
                    ],
                ],
            ]
        );
    }
}
