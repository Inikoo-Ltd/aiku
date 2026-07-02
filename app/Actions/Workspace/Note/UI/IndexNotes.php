<?php

namespace App\Actions\Workspace\Note\UI;

use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\Workspace\Note;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexNotes extends GrpAction
{
    use AsAction;
    use WithInertia;

    public function handle(?int $employeeId = null)
    {
        $query = Note::latest();
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        return $query->get();
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation(app('group'), $request);
        
        $employeeId = $request->user()->employee?->id;
        $notes = $this->handle($employeeId);
        
        return $this->htmlResponse($notes, $request);
    }
    
    public function htmlResponse($notes, ActionRequest $request): Response
    {
        $title = __('Notes');
        
        return Inertia::render(
            'Workspace/Notes/Index',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-sticky-note'],
                        'title' => $title,
                    ],
                ],
                'notes' => $notes,
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
                        'icon'  => 'fal fa-sticky-note',
                        'route' => [
                            'name'       => 'grp.workspace.notes.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Notes'),
                    ],
                ],
            ]
        );
    }
}
