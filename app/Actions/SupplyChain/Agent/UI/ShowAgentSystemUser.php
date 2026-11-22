<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Employee\UI\IndexEmployeeUserActivity;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\Agent\SystemUser\UpdateAgentSystemUser;
use App\Actions\SysAdmin\User\UI\IndexUserActions;
use App\Enums\UI\SupplyChain\AgentSystemUserTabsEnum;
use App\Http\Resources\CRM\WebUserLoginsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\EmployeeChatMessagesResource;
use App\Http\Resources\HumanResources\EmployeeSearchLogsResource;
use App\Http\Resources\SysAdmin\McpRequestsResource;
use App\Http\Resources\SysAdmin\UserRequestLogsResource;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentSystemUser extends OrgAction
{
    private Agent $agent;

    public function handle(User $user): User
    {
        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['supply-chain.view', 'compliance.view']);
    }

    public function asController(Agent $agent, User $user, ActionRequest $request): User
    {
        $this->agent = $agent;
        abort_unless(UpdateAgentSystemUser::make()->isAgentStaff($agent, $user), 404);
        $this->initialisationFromGroup($agent->group, $request)->withTab(AgentSystemUserTabsEnum::values());

        return $this->handle($user);
    }

    public function htmlResponse(User $user, ActionRequest $request): Response
    {
        $activity = IndexEmployeeUserActivity::make();

        $tabs = [
            AgentSystemUserTabsEnum::SHOWCASE->value   => fn () => $this->showcase($user),
            AgentSystemUserTabsEnum::LOGINS->value     => fn () => WebUserLoginsResource::collection($activity->logins($user, AgentSystemUserTabsEnum::LOGINS->value)),
            AgentSystemUserTabsEnum::REQUESTS->value   => fn () => UserRequestLogsResource::collection($activity->requests($user, AgentSystemUserTabsEnum::REQUESTS->value)),
            AgentSystemUserTabsEnum::SEARCHES->value   => fn () => EmployeeSearchLogsResource::collection($activity->searches($user, AgentSystemUserTabsEnum::SEARCHES->value)),
            AgentSystemUserTabsEnum::CHATS->value      => fn () => EmployeeChatMessagesResource::collection($activity->chats($user, AgentSystemUserTabsEnum::CHATS->value)),
            AgentSystemUserTabsEnum::AI_QUERIES->value => fn () => McpRequestsResource::collection($activity->aiQueries($user, AgentSystemUserTabsEnum::AI_QUERIES->value)),
            AgentSystemUserTabsEnum::CHANGES->value    => fn () => HistoryResource::collection(IndexUserActions::make()->handle($user, AgentSystemUserTabsEnum::CHANGES->value)),
            AgentSystemUserTabsEnum::HISTORY->value    => fn () => HistoryResource::collection(IndexHistory::run($user, AgentSystemUserTabsEnum::HISTORY->value)),
        ];

        return Inertia::render(
            'SupplyChain/AgentSystemUser',
            [
                'title'       => $user->username.' ('.$this->agent->organisation->code.')',
                'breadcrumbs' => $this->getBreadcrumbs($user, $request),
                'pageHead'    => [
                    'model' => __('Agent user'),
                    'icon'  => ['icon' => ['fal', 'fa-terminal'], 'title' => __('Agent user')],
                    'title' => $user->contact_name ?: $user->username,
                    'meta'  => [
                        [
                            'label'    => $user->username,
                            'leftIcon' => ['icon' => 'fal fa-user', 'tooltip' => __('Username')],
                        ],
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AgentSystemUserTabsEnum::navigation(),
                ],
                'management'  => IndexAgentSystemUsers::make()->management($this->agent, $request->user()),
                ...collect($tabs)->map(fn (Closure $resolver, string $tab) => $this->tab == $tab ? $resolver : Inertia::optional($resolver))->all(),
            ]
        )
            ->table($activity->loginsTableStructure(AgentSystemUserTabsEnum::LOGINS->value))
            ->table($activity->requestsTableStructure(AgentSystemUserTabsEnum::REQUESTS->value))
            ->table($activity->searchesTableStructure(AgentSystemUserTabsEnum::SEARCHES->value))
            ->table($activity->chatsTableStructure(AgentSystemUserTabsEnum::CHATS->value))
            ->table($activity->aiQueriesTableStructure(AgentSystemUserTabsEnum::AI_QUERIES->value))
            ->table(IndexUserActions::make()->tableStructure(prefix: AgentSystemUserTabsEnum::CHANGES->value))
            ->table(IndexHistory::make()->tableStructure(prefix: AgentSystemUserTabsEnum::HISTORY->value));
    }

    /**
     * @return array<string, mixed>
     */
    private function showcase(User $user): array
    {
        $stats    = $user->stats;
        $employee = $user->employees()->where('employees.organisation_id', $this->agent->organisation_id)->first();

        return [
            'id'                     => $user->id,
            'username'               => $user->username,
            'contact_name'           => $user->contact_name,
            'email'                  => $user->email,
            'is_active'              => (bool) $user->status,
            'positions'              => $employee ? $employee->jobPositions()->pluck('name')->all() : [],
            'organisations'          => $user->authorisedOrganisations()->pluck('organisations.name')->all(),
            'created_at'             => $user->created_at,
            'number_logins'          => $stats?->number_logins ?? 0,
            'last_login_at'          => $stats?->last_login_at,
            'last_login_ip'          => $stats?->last_login_ip,
            'number_failed_logins'   => $stats?->number_failed_logins ?? 0,
            'last_failed_login_at'   => $stats?->last_failed_login_at,
            'last_active'            => DB::table('user_requests')->where('user_id', $user->id)->max('date'),
            'has_2fa'                => filled($user->google2fa_secret),
            'is_two_factor_required' => (bool) $user->is_two_factor_required,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getBreadcrumbs(User $user, ActionRequest $request): array
    {
        $parameters = $request->route()->originalParameters();

        $agentCrumbs = ShowAgent::make()->getBreadcrumbs($this->agent, 'grp.supply-chain.agents.show', ['agent' => $parameters['agent']]);

        return array_merge($agentCrumbs, [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => $request->route()->getName(),
                        'parameters' => $parameters,
                    ],
                    'label' => $user->username,
                ],
            ],
        ]);
    }
}
