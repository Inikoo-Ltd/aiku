<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Actions\SupplyChain\Agent\SystemUser\StoreAgentSystemUser;
use App\Models\HumanResources\JobPosition;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgentSystemUsers
{
    use AsObject;

    /**
     * The agent's own staff: users authorised for the agent organisation and for no organisation
     * other than agents, so group staff who can open every organisation are left out.
     */
    public function handle(Agent $agent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('users.contact_name', $value)
                    ->orWhereStartWith('users.username', $value);
            });
        });

        return QueryBuilder::for(self::agentStaff($agent, User::query()))
            ->leftJoin('user_stats', 'user_stats.user_id', '=', 'users.id')
            ->defaultSort('username')
            ->select([
                'user_stats.number_logins', 'user_stats.last_login_at', 'user_stats.last_login_ip', 'user_stats.number_current_api_tokens', 'user_stats.number_expired_api_tokens',
                'users.id', 'users.username', 'users.email', 'users.slug', 'users.contact_name', 'users.image_id', 'users.status', 'users.is_two_factor_required', 'users.can_use_mcp',
                DB::raw("NULLIF(users.google2fa_secret, '') IS NOT NULL AS has_2fa"),
                DB::raw('(SELECT max(date) FROM user_requests WHERE user_requests.user_id = users.id) AS last_active'),
                DB::raw("(SELECT json_agg(json_build_object('code', job_positions.code, 'name', job_positions.name) ORDER BY job_positions.code) FROM user_has_models JOIN employees ON employees.id = user_has_models.model_id AND user_has_models.model_type = 'Employee' JOIN employee_has_job_positions ON employee_has_job_positions.employee_id = employees.id JOIN job_positions ON job_positions.id = employee_has_job_positions.job_position_id WHERE user_has_models.user_id = users.id AND employees.organisation_id = ".(int) $agent->organisation_id.") AS positions"),
            ])
            ->allowedSorts(['username', 'contact_name', 'status', 'last_login_at', 'number_logins', 'last_active', 'has_2fa'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder
     * @param TQuery $query
     * @return TQuery
     */
    public static function agentStaff(Agent $agent, $query)
    {
        return $query
            ->whereExists(fn ($query) => $query->from('user_has_authorised_models')
                ->whereColumn('user_has_authorised_models.user_id', 'users.id')
                ->where('user_has_authorised_models.model_type', 'Organisation')
                ->where('user_has_authorised_models.model_id', $agent->organisation_id))
            ->whereNotExists(fn ($query) => $query->from('user_has_authorised_models')
                ->join('organisations', 'organisations.id', '=', 'user_has_authorised_models.model_id')
                ->whereColumn('user_has_authorised_models.user_id', 'users.id')
                ->where('user_has_authorised_models.model_type', 'Organisation')
                ->where('organisations.type', '!=', OrganisationTypeEnum::AGENT->value));
    }

    /**
     * @return array{store_route: array{name: string, parameters: array<int>}, update_route: array{name: string, parameters: array<int>}, positions: array<int, array{value: string, label: string}>}|null
     */
    public function management(Agent $agent, User $viewer): ?array
    {
        if (!$viewer->authTo('supply-chain')) {
            return null;
        }

        return [
            'store_route'  => ['name' => 'grp.models.agent.system_user.store', 'parameters' => [$agent->id]],
            'update_route' => ['name' => 'grp.models.agent.system_user.update', 'parameters' => [$agent->id]],
            'positions'    => JobPosition::where('organisation_id', $agent->organisation_id)
                ->whereIn('code', StoreAgentSystemUser::POSITIONS)
                ->get(['code', 'name'])
                ->map(fn (JobPosition $position) => ['value' => $position->code, 'label' => __($position->name)])
                ->values()
                ->all(),
        ];
    }

    public function tableStructure(?string $prefix = null, bool $canManage = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $canManage) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withLabelRecord([__('user'), __('users')])
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('This agent has no system users')])
                ->column(key: 'status', label: __('Status'), type: 'icon', sortable: true)
                ->column(key: 'image', label: ['data' => ['fal', 'fa-user-circle'], 'type' => 'icon', 'tooltip' => __('Avatar')], type: 'avatar')
                ->column(key: 'username', label: __('Username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('Email'))
                ->column(key: 'last_login_at', label: __('Last login'), sortable: true, align: 'right')
                ->column(key: 'number_logins', label: __('Logins'), sortable: true, type: 'number')
                ->column(key: 'last_active', label: __('Last active'), sortable: true, align: 'right')
                ->column(key: 'has_2fa', label: ['data' => ['far', 'fa-shield-alt'], 'type' => 'icon', 'tooltip' => __('Has 2FA Enabled')], type: 'icon', sortable: true)
                ->defaultSort('username');

            if ($canManage) {
                $table->column(key: 'actions', label: '', align: 'right');
            }
        };
    }
}
