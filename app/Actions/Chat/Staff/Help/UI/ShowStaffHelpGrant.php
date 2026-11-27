<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Help\UI;

use App\Actions\SysAdmin\Group\UI\IndexHistoryInGroup;
use App\Http\Resources\History\HistoryResource;
use App\Models\Chat\StaffHelpGrant;
use App\Models\Helpers\Audit;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every change the helper made with the granter's access during one help grant, for the two people in it only.
 */
class ShowStaffHelpGrant
{
    use AsAction;

    public function handle(StaffHelpGrant $grant): LengthAwarePaginator
    {
        return QueryBuilder::for(Audit::class)
            ->where('group_id', $grant->group_id)
            ->where('created_at', '>=', $grant->granted_at)
            ->whereRaw("data->>'help_grant_id' = ?", [(string) $grant->id])
            ->orderBy('id', 'DESC')
            ->defaultSort('-created_at')
            ->withPaginator(null, tableName: 'staff_help_grant')
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        $grant = $request->route('staffHelpGrant');

        return in_array($request->user()->id, [$grant->granter_user_id, $grant->helper_user_id], true);
    }

    public function asController(StaffHelpGrant $staffHelpGrant, ActionRequest $request): Response
    {
        $staffHelpGrant->load(['granter', 'helper']);
        $title = __(':helper helping :granter', ['helper' => $staffHelpGrant->helper->contact_name, 'granter' => $staffHelpGrant->granter->contact_name]);
        $ended = $staffHelpGrant->ended_at ?? ($staffHelpGrant->expires_at->isPast() ? $staffHelpGrant->expires_at : null);

        return Inertia::render('SysAdmin/Histories', [
            'breadcrumbs' => [],
            'title'       => $title,
            'pageHead'    => [
                'icon'  => ['icon' => ['fal', 'fa-user-shield'], 'title' => __('Help')],
                'title' => $title,
                'afterTitle' => [
                    'label' => collect([
                        __('Granted :date for :minutes min', ['date' => $staffHelpGrant->granted_at->timezone($request->user()->timezone_name ?? 'UTC')->format('j M Y H:i'), 'minutes' => $staffHelpGrant->minutes]),
                        $staffHelpGrant->started_at ? null : __('never started'),
                        $ended ? __('ended :time (:reason)', ['time' => $ended->timezone($request->user()->timezone_name ?? 'UTC')->format('H:i'), 'reason' => __(str_replace('_', ' ', $staffHelpGrant->end_reason ?? StaffHelpGrant::EXPIRED))]) : __('still open'),
                    ])->filter()->implode(' · '),
                ],
            ],
            'data'        => HistoryResource::collection($this->handle($staffHelpGrant)),
        ])->table(IndexHistoryInGroup::make()->tableStructure());
    }
}
