<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery;
use App\Actions\CRM\Customer\GetCustomerFilterStructure;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Http\Resources\Comms\WhatsappCampaignRecipientsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappCampaignRecipients extends OrgAction
{
    use WithMarketingEditAuthorisation;

    private WhatsappCampaign $campaign;

    private array $channels = [];

    private array $customerFilters = [];

    private int $estimatedRecipients = 0;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $this->channels        = $this->readChannels();
        $this->customerFilters = $this->readCustomerFilters();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $term = strtolower($value);

            $query->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(recipients.name COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(recipients.phone_number COLLATE "C") LIKE ?', ["%{$term}%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $recipients = GetWhatsappRecipientsQuery::run($shop, $this->channels, $this->customerFilters);

        /* count() on the grouped query would return the size of the first group rather than
           the number of groups, so the estimate counts the wrapped subquery instead. */
        $this->estimatedRecipients = DB::query()->fromSub($recipients, 'estimate')->count();

        return QueryBuilder::for(Customer::query()->withoutGlobalScopes()->fromSub($recipients, 'recipients'))
            ->defaultSort('-last_visitor_message_at')
            ->allowedSorts(['name', 'phone_number', 'last_visitor_message_at', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Defaults to the contacted channel so an existing campaign opens on the audience it
     * had before the other two channels existed.
     */
    private function readChannels(): array
    {
        $requested = request()->input('channels');

        if (!is_array($requested)) {
            $requested = Arr::get($this->campaign->recipients_recipe ?? [], 'channels');
        }

        if (!is_array($requested) || empty(array_filter($requested, fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)))) {
            return ['contacted' => true, 'subscriber' => false, 'customers' => false];
        }

        $channels = [];

        foreach (GetWhatsappRecipientsQuery::CHANNELS as $channel) {
            $channels[$channel] = filter_var(Arr::get($requested, $channel, false), FILTER_VALIDATE_BOOLEAN);
        }

        return $channels;
    }

    private function readCustomerFilters(): array
    {
        $filters = request()->input('filters');

        if (!is_array($filters)) {
            $filters = Arr::get($this->campaign->recipients_recipe ?? [], 'customer_filters', []);
        }

        if (!is_array($filters)) {
            return [];
        }

        return array_diff_key($filters, ['all_customers' => true]);
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
                    'title' => __('No contacts yet'),
                    'count' => 0,
                ])
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'phone_number', label: __('Phone'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sources', label: __('Audience'), canBeHidden: false)
                ->column(key: 'last_visitor_message_at', label: __('Last message'), sortable: true, align: 'right')
                ->column(key: 'created_at', label: __('Created on'), sortable: true, align: 'right');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): LengthAwarePaginator
    {
        $this->campaign = $whatsappCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $recipients, ActionRequest $request): Response
    {
        $campaign        = $this->campaign;
        $routeParameters = [
            'organisation'     => $this->organisation->slug,
            'shop'             => $this->shop->slug,
            'whatsappCampaign' => $campaign->slug,
        ];

        return Inertia::render(
            'Org/Marketing/WhatsappCampaignRecipients',
            [
                'breadcrumbs' => $this->getBreadcrumbs($campaign, $request->route()->originalParameters()),
                'title'       => __('Recipients'),
                'pageHead'    => [
                    'title'      => __('Recipients'),
                    'model'      => $campaign->name,
                    'modelStyle' => 'text-sm',
                    'titleStyle' => 'font-normal text-lg',
                    'icon'       => [
                        'icon'  => ['fal', 'fa-users'],
                        'title' => __('Recipients'),
                    ],
                ],
                'selectedRecipients' => Arr::pluck($campaign->recipients_list ?? [], 'phone_number'),
                'channels'           => $this->channels,
                'filtersStructure'   => GetCustomerFilterStructure::run($this->shop),
                'filters'            => $this->customerFilters,
                'estimatedRecipients' => $this->estimatedRecipients,
                'shop_id'            => $this->shop->id,
                'shop_slug'          => $this->shop->slug,
                'updateRoute'        => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.update',
                    'parameters' => $routeParameters,
                    'method'     => 'patch',
                ],
                'backRoute'          => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.workshop',
                    'parameters' => $routeParameters,
                ],
                'customers' => WhatsappCampaignRecipientsResource::collection($recipients),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $recipients): AnonymousResourceCollection
    {
        return WhatsappCampaignRecipientsResource::collection($recipients);
    }

    public function getBreadcrumbs(WhatsappCampaign $campaign, array $routeParameters): array
    {
        return array_merge(
            ShowWhatsappCampaignWorkshop::make()->getBreadcrumbs($campaign, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => '#',
                        'label' => __('Recipients'),
                    ],
                ],
            ],
        );
    }
}
