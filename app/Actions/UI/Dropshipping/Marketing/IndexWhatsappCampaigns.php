<?php

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\WithWhatsappCampaignsSubNavigation;
use App\Http\Resources\Comms\WhatsappCampaignsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappCampaigns extends OrgAction
{
    use WithWhatsappCampaignsSubNavigation;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("marketing.{$this->shop->id}.view");
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('whatsapp_campaigns.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WhatsappCampaign::class)
            ->leftJoin('meta_message_templates', 'whatsapp_campaigns.meta_message_template_id', '=', 'meta_message_templates.id')
            ->where('whatsapp_campaigns.shop_id', $shop->id)
            ->defaultSort('-whatsapp_campaigns.created_at')
            ->select([
                'whatsapp_campaigns.id',
                'whatsapp_campaigns.slug',
                'whatsapp_campaigns.name',
                'whatsapp_campaigns.state',
                'whatsapp_campaigns.type',
                'whatsapp_campaigns.recipients_count',
                'whatsapp_campaigns.scheduled_at',
                'whatsapp_campaigns.sent_at',
                'whatsapp_campaigns.created_at',
                'meta_message_templates.name as template_name',
            ])
            ->allowedSorts(['name', 'state', 'type', 'recipients_count', 'scheduled_at', 'sent_at', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
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
                    'title' => __('No campaigns yet'),
                    'count' => 0,
                ])
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'template_name', label: __('Template'), canBeHidden: false)
                ->column(key: 'type_label', label: __('Type'))
                ->column(key: 'recipients_count', label: __('Recipients'), sortable: true, align: 'right')
                ->column(key: 'scheduled_at', label: __('Scheduled'), sortable: true, align: 'right')
                ->column(key: 'sent_at', label: __('Sent'), sortable: true, align: 'right');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $campaigns, ActionRequest $request): Response
    {
        $title = __('Whatsapp Campaigns');

        return Inertia::render(
            'Org/Marketing/WhatsappCampaigns',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => $title,
                    ],
                    'subNavigation' => $this->getSubNavigation($request),
                    'actions'       => [
                        [
                            // ponytail: no route yet, the create action lands later
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Campaign'),
                        ],
                    ],
                ],
                'data' => WhatsappCampaignsResource::collection($campaigns),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $campaigns): AnonymousResourceCollection
    {
        return WhatsappCampaignsResource::collection($campaigns);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Whatsapp Campaigns'),
                        'icon'  => 'fab fa-whatsapp'
                    ],
                ],
            ],
        );
    }
}
