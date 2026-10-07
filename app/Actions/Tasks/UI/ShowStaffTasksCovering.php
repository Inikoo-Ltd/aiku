<?php

namespace App\Actions\Tasks\UI;

use App\Actions\HumanResources\Leave\GetCoveredWork;
use App\Actions\HumanResources\Leave\GetUserLeaveCovers;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffTasksCovering extends OrgAction
{
    use WithStaffTasksScope;

    /**
     * The colleagues the viewer covers, each with the open work assigned to them that someone has to pick up.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $viewer): array
    {
        $leaveCovers = GetUserLeaveCovers::make();

        return $leaveCovers->coveredLeaves($viewer)
            ->map(fn (Leave $leave) => [
                ...$leaveCovers->summary($leave),
                'work' => GetCoveredWork::run($leaveCovers->absentUserIds($leave)),
            ])
            ->all();
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($request->user());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($request->user());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($request->user());
    }

    public function htmlResponse(array $covers): Response
    {
        $title = __('Covering');

        return Inertia::render('Tasks/StaffTasksCovering', [
            'breadcrumbs' => array_merge(
                $this->tasksBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => $this->tasksRoute('covering'), 'label' => $title]]]
            ),
            'title'       => $title,
            'pageHead'    => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-user-friends'], 'title' => $title]],
            'covers'      => $covers,
        ]);
    }
}
