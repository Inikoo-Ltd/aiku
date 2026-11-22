<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The claims customers made themselves (HELP-4869): Pending is customer services' queue, oldest
 * first; Done lists settled and rejected claims, newest first, filtered by state.
 */
class IndexCustomerClaims extends OrgAction
{
    use WithCustomerClaimsSection;

    private Shop $parent;

    private bool $pending = true;

    /**
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}>
     */
    protected function getElementGroups(Shop $shop): array
    {
        if ($this->pending) {
            return [];
        }

        $counts = CustomerClaim::where('shop_id', $shop->id)->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => collect([CustomerClaimStateEnum::SETTLED, CustomerClaimStateEnum::REJECTED])->mapWithKeys(fn (CustomerClaimStateEnum $state) => [$state->value => [$state->label(), (int) $counts->get($state->value, 0)]])->all(),
                'engine'   => fn ($query, array $elements) => $query->whereIn('customer_claims.state', $elements),
            ],
        ];
    }

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('customer_claims.reference', $value)
                    ->orWhereAnyWordStartWith('customers.name', $value);
            });
        });

        $query = QueryBuilder::for(CustomerClaim::class)
            ->where('customer_claims.shop_id', $shop->id)
            ->where('customer_claims.state', $this->pending ? '=' : '!=', CustomerClaimStateEnum::IN_REVIEW->value);

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $query->whereElementGroup(key: $key, allowedElements: array_keys($elementGroup['elements']), engine: $elementGroup['engine'], prefix: $prefix);
        }

        return $query
            ->leftJoin('customers', 'customers.id', '=', 'customer_claims.customer_id')
            ->leftJoin('orders', 'orders.id', '=', 'customer_claims.order_id')
            ->select([
                'customer_claims.id',
                'customer_claims.ulid',
                'customer_claims.reference',
                'customer_claims.state',
                'customer_claims.source',
                'customer_claims.kind',
                'customer_claims.resolution',
                'customer_claims.amount',
                'customer_claims.currency_code',
                'customer_claims.created_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
            ])
            ->defaultSort($this->pending ? 'created_at' : '-created_at')
            ->allowedSorts(['created_at', 'reference', 'amount'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state', 'customer_claims.state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => $this->pending ? __('No claims waiting for review') : __('No claims settled or rejected yet')])
                ->column(key: 'state', label: '', type: 'icon', canBeHidden: false)
                ->column(key: 'reference', label: __('Claim'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Date'), type: 'date', canBeHidden: false, sortable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, searchable: true)
                ->column(key: 'order_reference', label: __('Order'), canBeHidden: false)
                ->column(key: 'kind', label: __('Kind'), canBeHidden: false)
                ->column(key: 'resolution', label: __('Outcome'), canBeHidden: false)
                ->column(key: 'amount', label: __('Credit'), type: 'number', canBeHidden: false, sortable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $claims, ActionRequest $request): Response
    {
        return Inertia::render('Org/Shop/CRM/CustomerClaims', [
            'breadcrumbs' => $this->claimsBreadcrumbs($this->parent, [
                'route' => ['name' => $request->route()->getName(), 'parameters' => [$this->parent->organisation->slug, $this->parent->slug]],
                'label' => $this->pending ? __('Pending') : __('Done'),
            ]),
            'title'       => $this->pending ? __('Pending claims') : __('Done claims'),
            'pageHead'    => [
                'title'         => $this->pending ? __('Pending claims') : __('Done claims'),
                'icon'          => ['icon' => ['fal', $this->pending ? 'fa-hourglass-half' : 'fa-archive'], 'title' => __('Claims')],
                'model'         => $this->parent->name,
                'subNavigation' => $this->claimsSubNavigation($this->parent),
            ],
            'data'        => $claims->through(fn (CustomerClaim $claim) => [
                'ulid'            => $claim->ulid,
                'reference'       => $claim->reference,
                'state'           => $claim->state->value,
                'state_label'     => $claim->state->label(),
                'source'          => $claim->source->value,
                'kind'            => $claim->kind->label(),
                'resolution'      => ShowCustomerClaim::resolutionLabel($claim->resolution),
                'amount'          => $claim->resolution === 'refund_to_balance' ? (float) $claim->amount : null,
                'currency_code'   => $claim->currency_code,
                'created_at'      => $claim->created_at,
                'customer_name'   => $claim->customer_name,
                'customer_slug'   => $claim->customer_slug,
                'order_reference' => $claim->order_reference,
                'order_slug'      => $claim->order_slug,
            ]),
        ])->table($this->tableStructure($this->parent));
    }

    public function pending(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent  = $shop;
        $this->pending = true;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function done(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent  = $shop;
        $this->pending = false;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
