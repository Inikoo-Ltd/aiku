<?php

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\OrgAction;
use App\Actions\Traits\WithWhatsappCampaignsSubNavigation;
use App\Http\Resources\Comms\WhatsappSubscribersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappSubscribers extends OrgAction
{
    use WithWhatsappCampaignsSubNavigation;

    private const NAME_EXPRESSION = 'coalesce(customers.contact_name, meta_chat_sessions.guest_identifier)';

    private const PHONE_EXPRESSION = 'coalesce(customers.phone, meta_chat_sessions.phone_number)';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("marketing.{$this->shop->id}.view");
    }

    /**
     * whatsapp_subscribers carries no name or phone of its own, both are resolved through the
     * polymorphic parent using the morph aliases registered in AppServiceProvider.
     */
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $term = strtolower($value);

            $query->where(function ($query) use ($term) {
                $query->whereRaw('LOWER('.self::NAME_EXPRESSION.' COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER('.self::PHONE_EXPRESSION.' COLLATE "C") LIKE ?', ["%{$term}%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WhatsappSubscriber::class)
            ->leftJoin('customers', function ($join) {
                $join->on('whatsapp_subscribers.parent_id', '=', 'customers.id')
                    ->where('whatsapp_subscribers.parent_type', '=', 'Customer');
            })
            ->leftJoin('meta_chat_sessions', function ($join) {
                $join->on('whatsapp_subscribers.parent_id', '=', 'meta_chat_sessions.id')
                    ->where('whatsapp_subscribers.parent_type', '=', 'MetaChatSession');
            })
            ->where('whatsapp_subscribers.shop_id', $shop->id)
            ->select([
                'whatsapp_subscribers.id',
                'whatsapp_subscribers.opt_in_method',
                'whatsapp_subscribers.parent_type',
                'whatsapp_subscribers.created_at',
                DB::raw(self::NAME_EXPRESSION.' as name'),
                DB::raw(self::PHONE_EXPRESSION.' as phone_number'),
                'customers.slug as customer_slug',
            ])
            ->defaultSort('-whatsapp_subscribers.created_at')
            ->allowedSorts(['name', 'phone_number', 'opt_in_method', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No subscribers yet'),
                    'count' => 0,
                ])
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'phone_number', label: __('Phone'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opt_in_method', label: __('Opt-in'), sortable: true)
                ->column(key: 'created_at', label: __('Subscribed on'), sortable: true, align: 'right');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $subscribers, ActionRequest $request): Response
    {
        $title = __('Subscribers');

        return Inertia::render(
            'Org/Marketing/WhatsappSubscribers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-users'],
                        'title' => $title,
                    ],
                    'subNavigation' => $this->getSubNavigation($request),
                ],
                'subscribers' => WhatsappSubscribersResource::collection($subscribers),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $subscribers): AnonymousResourceCollection
    {
        return WhatsappSubscribersResource::collection($subscribers);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexWhatsappCampaigns::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.subscribers.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Subscribers'),
                        'icon'  => 'fal fa-users'
                    ],
                ],
            ],
        );
    }
}
