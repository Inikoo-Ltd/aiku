<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery;
use App\Actions\Comms\WhatsappCampaign\WithWhatsappCampaignAudience;
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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappCampaignRecipients extends OrgAction
{
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignAudience;

    private WhatsappCampaign $campaign;

    private array $channels = [];

    private array $customerFilters = [];

    private array $templateTags = [];

    private ?array $survivingKeys = null;

    /**
     * How many pending keys one reload may ask about, matching the cap a single save carries
     * in StoreWhatsappCampaignRecipients. It bounds the IN list this builds from request input.
     */
    private const PENDING_KEY_CAP = 5000;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $this->channels        = $this->readAudienceChannels(request()->input('channels'), $this->campaign);
        $this->customerFilters = $this->readAudienceCustomerFilters(request()->input('filters'), $this->campaign);
        $this->templateTags    = $this->readTemplateTags($this->campaign);

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

        $recipients = $this->audienceQuery($this->campaign, $this->channels, $this->customerFilters);

        $this->survivingKeys = $this->keysStillInAudience($recipients, request()->input('pending_keys'));

        return QueryBuilder::for($this->markStoredRecipients($recipients))
            ->defaultSort('-last_visitor_message_at')
            ->allowedSorts(['name', 'phone_number', 'last_visitor_message_at', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Tells each row whether the campaign already holds it, as a column on the row itself.
     *
     * Asked per row rather than as a list of everything stored: the picker is paginated, so
     * only the contacts on screen need an answer, and a campaign holding a whole shop would
     * otherwise have to be enumerated into memory, into a bind list and into a prop just to
     * tick twenty five checkboxes.
     *
     * The exists sits on the outer query so it is evaluated after the paginator has cut the
     * page down, and it reads the (whatsapp_campaign_id, phone) unique index directly.
     *
     * A recipient a send has already claimed still counts as stored: they are being messaged,
     * and showing them unticked would misdescribe the campaign. The picker cannot drop them,
     * which StoreWhatsappCampaignRecipients enforces on its own.
     */
    private function markStoredRecipients(Builder $recipients): EloquentBuilder
    {
        $isStored = DB::table('whatsapp_recipients')
            ->selectRaw('1')
            ->whereColumn('whatsapp_recipients.phone', 'recipients.recipient_key')
            ->where('whatsapp_recipients.whatsapp_campaign_id', $this->campaign->id);

        /* recipients.* is restored explicitly because naming any column stops the paginator
           from selecting everything, which would otherwise strip the row down to this flag. */
        return Customer::query()
            ->withoutGlobalScopes()
            ->fromSub($recipients, 'recipients')
            ->select('recipients.*')
            ->selectRaw('exists ('.$isStored->toSql().') as is_selected')
            ->addBinding($isStored->getBindings(), 'select');
    }

    /**
     * Which of the contacts the page has ticked are still in the audience it is now showing.
     *
     * The picker keeps its ticks as phone keys and survives a filter change untouched, because
     * the reload preserves page state. Without this the ticks would go on describing contacts
     * the new filter excludes: counted in the heading, sent on save, and then dropped by the
     * save's own re-run of the audience query, so the count would promise more than it stores.
     *
     * Answered here rather than on the page because the browser only ever holds one page of
     * contacts and cannot tell whether a key it ticked three filters ago still matches.
     *
     * Runs against the audience subquery before the paginator narrows it, so paging and the
     * global search term leave the answer alone: searching is not a statement about who is
     * selected.
     *
     * Null when the page asked nothing, which is not the same as an empty answer: only the
     * filter reload carries the ticks, so paging, sorting and searching arrive without them.
     * Answering [] there would tell the page every tick had fallen out of the audience, and
     * it would prune the lot.
     *
     * @param  mixed  $requested  raw request input, shaped by whoever called us
     * @return array<int, string>|null
     */
    private function keysStillInAudience(Builder $recipients, mixed $requested): ?array
    {
        if (!is_array($requested)) {
            return null;
        }

        $keys = array_values(array_unique(array_filter(array_map(
            function ($phone) {
                if (!is_scalar($phone) || !GetWhatsappRecipientsQuery::isSendablePhone((string) $phone)) {
                    return '';
                }

                return GetWhatsappRecipientsQuery::normalisePhoneKey((string) $phone);
            },
            array_slice($requested, 0, self::PENDING_KEY_CAP)
        ))));

        if (!$keys) {
            return [];
        }

        return DB::query()
            ->fromSub($recipients, 'audience')
            ->whereIn('audience.recipient_key', $keys)
            ->pluck('audience.recipient_key')
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
                'templateTags'       => $this->templateTags,
                /* The count comes from the rows rather than from anything the page counts for
                   itself: the browser only ever sees one page of contacts, so it has no way to
                   total an audience it never receives. */
                'recipientsCount'    => $campaign->recipients_count,
                /* Of the contacts the page said it had ticked, the ones this audience still
                   holds. The page prunes its selection down to these, and leaves it alone when
                   this is null, which is what a reload carrying no ticks answers. */
                'survivingKeys'      => $this->survivingKeys,
                'channels'           => $this->channels,
                'filtersStructure'   => GetCustomerFilterStructure::run($this->shop),
                'filters'            => $this->customerFilters,
                'shop_id'            => $this->shop->id,
                'shop_slug'          => $this->shop->slug,
                'storeRoute'         => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.recipients.store',
                    'parameters' => $routeParameters,
                    'method'     => 'post',
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
