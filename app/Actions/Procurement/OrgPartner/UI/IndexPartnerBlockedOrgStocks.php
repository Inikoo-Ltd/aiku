<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPartnerBlockedOrgStocks extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;

    /**
     * @return Builder<OrgStock>
     */
    public static function blockedQuery(OrgPartner $orgPartner): Builder
    {
        return OrgStock::query()
            ->where('org_stocks.organisation_id', $orgPartner->organisation_id)
            ->where('org_stocks.is_excluded_from_auto_ordering', true)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('org_stocks as partner_org_stocks')
                ->whereColumn('partner_org_stocks.stock_id', 'org_stocks.stock_id')
                ->where('partner_org_stocks.organisation_id', $orgPartner->partner_id)
                ->where('partner_org_stocks.state', OrgStockStateEnum::ACTIVE->value));
    }

    /**
     * Our SKOs this partner sells that staff marked "Do not auto order", so they can be unblocked.
     */
    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        return QueryBuilder::for(self::blockedQuery($orgPartner))
            ->select(['org_stocks.id', 'org_stocks.code', 'org_stocks.name', 'org_stocks.quantity_available', 'org_stocks.health_rank', 'org_stocks.updated_at'])
            ->defaultSort('code')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', 'quantity_available', 'health_rank'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('product'), __('products')])
                ->withEmptyState([
                    'title'       => __('No blocked products'),
                    'description' => __('Products removed with "Don\'t suggest again" show here'),
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('SKO description'), canBeHidden: false, sortable: true)
                ->column(key: 'health_rank', label: __('Rank'), canBeHidden: false, sortable: true)
                ->column(key: 'quantity_available', label: __('Our stock'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $orgStocks, ActionRequest $request): Response
    {
        $title = __('Blocked');

        return Inertia::render(
            'Procurement/PartnerBlockedOrgStocks',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => '('.$this->orgPartner->partner->code.') '.$title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-ban'],
                        'title' => $title,
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => $title,
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner'  => ['id' => $this->orgPartner->id],
                'canEdit'     => $request->user()->authTo("procurement.{$this->organisation->id}.edit"),
                'data'        => $orgStocks,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowPartnerShoppingDashboard::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.shopping_list.blocked',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Blocked'),
                        'icon'  => 'fal fa-ban',
                    ],
                ],
            ]
        );
    }
}
