<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\UI\EditOrganisationSettings;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Dispatching\CrossDispatchSettingsResource;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCrossDispatchSettings extends OrgAction
{
    public static function canBeManagedBy(User $user, Organisation $organisation): bool
    {
        return $user->authTo([
            'organisations.edit',
            'org-admin.'.$organisation->id,
        ]);
    }

    /**
     * One row per direction the organisation takes part in: orders of a company with a dropshipping shop, dispatched by a company with a warehouse.
     */
    public static function directionsQuery(Organisation $organisation): Builder
    {
        return OrgPartner::query()
            ->whereColumn('org_partners.organisation_id', '!=', 'org_partners.partner_id')
            ->where(fn (Builder $query) => $query->where('org_partners.organisation_id', $organisation->id)->orWhere('org_partners.partner_id', $organisation->id))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('shops')
                ->whereColumn('shops.organisation_id', 'org_partners.organisation_id')
                ->where('shops.type', ShopTypeEnum::DROPSHIPPING->value)
                ->whereNull('shops.deleted_at'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('warehouses')
                ->whereColumn('warehouses.organisation_id', 'org_partners.partner_id')
                ->whereNull('warehouses.deleted_at'));
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canBeManagedBy($request->user(), $this->organisation);
    }

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(self::directionsQuery($organisation))
            ->with(['organisation', 'partner.currency'])
            ->orderByRaw('org_partners.organisation_id = ? desc', [$organisation->id])
            ->orderBy('org_partners.id')
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Changes to the directions on this screen, whichever of the two companies made them: an audit is filed under the ordering company's row, so it is found by the row, never by the audit's organisation.
     */
    public function history(Organisation $organisation): LengthAwarePaginator
    {
        $labels = self::directionsQuery($organisation)
            ->with(['organisation', 'partner'])
            ->get()
            ->mapWithKeys(fn (OrgPartner $orgPartner) => [$orgPartner->id => $orgPartner->organisation->name.' → '.$orgPartner->partner->name])
            ->all();

        return IndexHistory::run(new OrgPartner(), 'history', eventScopeFilter: 'updated', auditScope: [
            ['type' => 'OrgPartner', 'labels' => $labels ?: [0 => ''], 'shops' => null],
        ]);
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState(['title' => __('No other company can dispatch dropshipping orders with this one')])
                ->column(key: 'origin_name', label: __('Orders of'), canBeHidden: false)
                ->column(key: 'dispatcher_name', label: __('Dispatched by'), canBeHidden: false)
                ->column(key: 'our_enabled', label: __('We allow it'), canBeHidden: false)
                ->column(key: 'their_enabled', label: __('They allow it'), canBeHidden: false)
                ->column(key: 'is_on', label: __('Status'), canBeHidden: false)
                ->column(key: 'goods_price_basis', label: __('Goods charged at'), canBeHidden: false)
                ->column(key: 'dispatch_fee_per_parcel', label: __('Fee per parcel'), canBeHidden: false, align: 'right')
                ->column(key: 'shipping_pass_through', label: __('Shipping'), canBeHidden: false)
                ->column(key: 'settlement_period', label: __('Invoiced'), canBeHidden: false)
                ->column(key: 'allow_customs_crossing', label: __('Customs border'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $directions, ActionRequest $request): Response
    {
        $title = __('Dispatch from other warehouses');

        return Inertia::render(
            'Org/Dispatching/CrossDispatchSettings',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => ['icon' => ['fal', 'fa-truck-container'], 'title' => $title],
                    'title' => $title,
                ],
                'options'     => OrgPartner::CROSS_DISPATCH_OPTIONS,
                'data'        => CrossDispatchSettingsResource::collection($directions),
                'history'     => HistoryResource::collection($this->history($this->organisation)),
            ]
        )->table($this->tableStructure())
            ->table(IndexHistory::make()->tableStructure(prefix: 'history', withRecordColumn: true));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            EditOrganisationSettings::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.settings.cross_dispatch.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Dispatch from other warehouses'),
                    ],
                ],
            ]
        );
    }
}
