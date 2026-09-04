<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Comms\WhatsappCampaign\FilterRecipientsByTemplateTags;
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
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
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

    private array $validSelection = [];

    private array $templateTags = [];

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $this->channels        = $this->readChannels();
        $this->customerFilters = $this->readCustomerFilters();
        $this->templateTags    = $this->readTemplateTags();

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

        $recipients = FilterRecipientsByTemplateTags::run(
            GetWhatsappRecipientsQuery::run($shop, $this->channels, $this->customerFilters),
            $this->templateTags
        );

        $this->validSelection = $this->readValidSelection($recipients);

        return QueryBuilder::for(Customer::query()->withoutGlobalScopes()->fromSub($recipients, 'recipients'))
            ->defaultSort('-last_visitor_message_at')
            ->allowedSorts(['name', 'phone_number', 'last_visitor_message_at', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Defaults to the subscriber channel, the audience that opted in to being messaged.
     */
    private function readChannels(): array
    {
        $requested = request()->input('channels');

        if (!is_array($requested)) {
            $requested = Arr::get($this->campaign->recipients_recipe ?? [], 'channels');
        }

        if (!is_array($requested) || empty(array_filter($requested, fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)))) {
            return GetWhatsappRecipientsQuery::DEFAULT_CHANNELS;
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

    /**
     * The merge tags the campaign's template was written with, read from the same path
     * SendWhatsappDeliveryChannel fills them from. A recipient who cannot supply one of
     * them is not sent to, so the picker leaves them out rather than letting the campaign
     * count contacts it will only fail on.
     *
     * @return array<int, string>
     */
    private function readTemplateTags(): array
    {
        $tags = Arr::get($this->campaign->metaMessageTemplate?->data ?? [], 'merge_tags.body', []);

        return is_array($tags) ? $tags : [];
    }

    /**
     * The keys the page may keep ticked: the current selection narrowed to the rows the
     * audience still holds. The table is paginated, so the browser cannot judge a selection
     * sitting on a page it never loaded, and a channel it unticks would otherwise leave
     * those rows saved into recipients_list and messaged.
     *
     * The selection is read from the request while the user is toggling channels and falls
     * back to what the campaign has stored on first load.
     *
     * @return array<int, string>
     */
    private function readValidSelection(Builder $recipients): array
    {
        $selection = request()->input('selection');

        if (!is_array($selection)) {
            $selection = Arr::pluck($this->campaign->recipients_list ?? [], 'phone_number');
        }

        $phoneKeys = array_values(array_unique(array_filter(array_map(
            fn ($phone) => GetWhatsappRecipientsQuery::normalisePhoneKey(is_string($phone) ? $phone : null),
            $selection
        ))));

        if (!$phoneKeys) {
            return [];
        }

        return DB::query()
            ->fromSub($recipients, 'selection')
            ->whereIn('selection.recipient_key', $phoneKeys)
            ->pluck('selection.recipient_key')
            ->all();
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

    /**
     * The audience is only meaningful once a template is chosen: its merge tags decide who
     * can be reached at all, and a selection made without them would be picked against no
     * requirement and then dropped at send. The workshop hides the link, this closes the
     * direct URL behind it.
     */
    public function htmlResponse(LengthAwarePaginator $recipients, ActionRequest $request): Response|RedirectResponse
    {
        $campaign        = $this->campaign;

        if (!$campaign->meta_message_template_id) {
            return Redirect::route(
                'grp.org.shops.show.marketing.whatsapp_campaigns.workshop',
                [$this->organisation->slug, $this->shop->slug, $campaign->slug]
            )->with('notification', [
                'status' => 'error',
                'title'  => __('Choose a template first'),
                'description' => __('A template decides what information each contact has to supply, so recipients are chosen after it.'),
            ]);
        }

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
                'validSelection'     => $this->validSelection,
                'templateTags'       => $this->templateTags,
                'channels'           => $this->channels,
                'filtersStructure'   => GetCustomerFilterStructure::run($this->shop),
                'filters'            => $this->customerFilters,
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
