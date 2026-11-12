<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ShoppingAssistant\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\UI\Web\ShoppingAssistantPerformanceTabsEnum;
use App\Http\Resources\Web\ShoppingAssistantConversationsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * AI Assistant performance (HELP-4608): on WebOps for every website of the group, to group-wide
 * viewers only, and in a website's analytics for that website.
 */
class ShowShoppingAssistantPerformance extends OrgAction
{
    use WithWebAuthorisation {
        authorize as authorizeWebsiteRoute;
    }
    use WithWebsiteAnalyticsSubNavigation;

    public const array OUTCOMES = ['ordered', 'added', 'clicked', 'shown'];

    private ?Website $website = null;

    private bool $inShopMenu = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->inShopMenu) {
            return $request->user()->authTo(["websites-view.{$this->organisation->id}", "web.{$this->shop->id}.view", 'group-webmaster.view']);
        }

        return $this->website ? $this->authorizeWebsiteRoute($request) : $this->canViewGroupShoppingAssistant($request->user());
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisationFromGroup(group(), $request)->withTab(ShoppingAssistantPerformanceTabsEnum::values());

        return $request;
    }

    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): ActionRequest
    {
        abort_unless($website->shop_id === $shop->id, 404);
        $this->website = $website;
        $this->initialisationFromShop($shop, $request)->withTab(ShoppingAssistantPerformanceTabsEnum::values());

        return $request;
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        abort_unless($shop->website, 404);
        $this->forShopMenu($shop->website);
        $this->initialisationFromShop($shop, $request)->withTab(ShoppingAssistantPerformanceTabsEnum::values());

        return $request;
    }

    public function forShopMenu(Website $website): static
    {
        $this->website    = $website;
        $this->inShopMenu = true;

        return $this;
    }

    public function forWebsite(Website $website): static
    {
        $this->website = $website;

        return $this;
    }

    /**
     * Websites the page reports on: the route's website, or every website of the group.
     *
     * @return Collection<int, Website>
     */
    public function scopeWebsites(?Website $website): Collection
    {
        return $website ? collect([$website]) : Website::where('group_id', group()->id)->orderBy('name')->get();
    }

    /**
     * The filters of the query string; a value the page does not offer is dropped.
     *
     * @param  Collection<int, Website>  $websites
     */
    public function filters(ActionRequest $request, Collection $websites, bool $websiteLocked): array
    {
        $date = function (mixed $value): ?CarbonImmutable {
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return null;
            }

            return CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        };

        $to   = $date($request->query('to')) ?? CarbonImmutable::today();
        $from = $date($request->query('from'));
        if (!$from || $from->gt($to)) {
            $from = $to->subDays(29);
        }

        $options = $this->options($websites, $websiteLocked);
        $pick    = fn (string $key, array $values) => in_array($request->query($key), $values, true) ? $request->query($key) : null;

        $websiteId  = $websiteLocked ? null : collect($options['websites'])->pluck('value')->first(fn ($id) => is_scalar($request->query('website')) && (string) $id === (string) $request->query('website'));
        $websiteIds = $websiteId ? [$websiteId] : $websites->pluck('id')->all();
        $language   = $pick('language', array_column($options['languages'], 'value'));

        $days = (int) $from->diffInDays($to) + 1;

        return [
            'from'          => $from,
            'to'            => $to,
            'previous_from' => $from->subDays($days),
            'previous_to'   => $from->subDay(),
            'website'       => $websiteId,
            'language'      => $language,
            'entry_point'   => $pick('entry_point', array_column($options['entry_points'], 'value')),
            'shop_type'     => $pick('shop_type', CustomerShopTypeEnum::values()),
            'country'       => $pick('country', array_column($options['countries'], 'value')),
            'outcome'       => $pick('outcome', self::OUTCOMES),
            'website_ids'   => $websiteIds,
            'search_website_ids' => $language
                ? DB::table('websites')->join('shops', 'shops.id', '=', 'websites.shop_id')->join('languages', 'languages.id', '=', 'shops.language_id')
                    ->whereIn('websites.id', $websiteIds)->where('languages.code', $language)->pluck('websites.id')->all()
                : $websiteIds,
            'website_locked' => $websiteLocked,
            'options'       => $options,
        ];
    }

    /**
     * @param  Collection<int, Website>  $websites
     */
    private function options(Collection $websites, bool $websiteLocked): array
    {
        $websiteIds = $websites->pluck('id')->all();

        return [
            'websites'     => $websiteLocked ? [] : $websites->map(fn (Website $website) => ['value' => $website->id, 'label' => $website->name ?: $website->domain])->values()->all(),
            'languages'    => DB::table('websites')
                ->join('shops', 'shops.id', '=', 'websites.shop_id')
                ->join('languages', 'languages.id', '=', 'shops.language_id')
                ->whereIn('websites.id', $websiteIds)
                ->distinct()
                ->orderBy('languages.name')
                ->get(['languages.code', 'languages.name'])
                ->map(fn ($language) => ['value' => $language->code, 'label' => $language->name])->all(),
            'entry_points' => collect(ShoppingAssistantEntryPointEnum::cases())
                ->reject(fn (ShoppingAssistantEntryPointEnum $entryPoint) => $entryPoint === ShoppingAssistantEntryPointEnum::COMMAND)
                ->map(fn (ShoppingAssistantEntryPointEnum $entryPoint) => ['value' => $entryPoint->value, 'label' => self::entryPointLabel($entryPoint->value)])
                ->values()->all(),
            'shop_types'   => collect(CustomerShopTypeEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'countries'    => DB::table('shopping_assistant_conversations')
                ->join('customers', 'customers.id', '=', 'shopping_assistant_conversations.customer_id')
                ->leftJoin('countries', 'countries.code', '=', DB::raw("customers.location->>0"))
                ->whereIn('shopping_assistant_conversations.website_id', $websiteIds)
                ->where('shopping_assistant_conversations.entry_point', '!=', ShoppingAssistantEntryPointEnum::COMMAND->value)
                ->where('customers.is_staff', false)
                ->whereRaw("customers.location->>0 is not null")
                ->selectRaw("distinct customers.location->>0 as code, countries.name")
                ->orderBy('code')
                ->get()
                ->map(fn ($country) => ['value' => $country->code, 'label' => $country->name ?: $country->code])->all(),
        ];
    }

    public static function entryPointLabel(string $entryPoint): string
    {
        return match ($entryPoint) {
            ShoppingAssistantEntryPointEnum::PROFILE->value => __('Profile'),
            ShoppingAssistantEntryPointEnum::SEARCH->value  => __('Search'),
            ShoppingAssistantEntryPointEnum::CHAT->value    => __('Chat'),
            default                                         => __('Command line'),
        };
    }

    /**
     * The page currency: the shop's on a website route, the group's on WebOps.
     *
     * @return array{0: string, 1: bool}
     */
    public function currency(?Website $website): array
    {
        return $website ? [$website->shop->currency->code, true] : [group()->currency->code, false];
    }

    public function conversationsTable(array $filters, string $amountColumn, ?float $usdRate, string $prefix = ShoppingAssistantPerformanceTabsEnum::CONVERSATIONS->value): LengthAwarePaginator
    {
        $report = GetShoppingAssistantReport::make();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('customers.name ilike ?', ["%$value%"])
                    ->orWhereRaw('customers.reference ilike ?', ["%$value%"])
                    ->orWhereExists(fn ($query) => $query->select(DB::raw(1))
                        ->from('shopping_assistant_messages')
                        ->whereColumn('shopping_assistant_messages.shopping_assistant_conversation_id', 'shopping_assistant_conversations.id')
                        ->where('shopping_assistant_messages.role', 'customer')
                        ->whereRaw('shopping_assistant_messages.body ilike ?', ["%$value%"]));
            });
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        $query = QueryBuilder::for(ShoppingAssistantConversation::class)
            ->leftJoin('customers', 'customers.id', '=', 'shopping_assistant_conversations.customer_id')
            ->join('websites', 'websites.id', '=', 'shopping_assistant_conversations.website_id')
            ->join('shops', 'shops.id', '=', 'shopping_assistant_conversations.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shopping_assistant_conversations.organisation_id');

        $report->applyScope($query->getEloquentBuilder(), $filters, $filters['from']->startOfDay(), $filters['to']->endOfDay());

        $eventCount = fn (string $type) => "(select count(*) from shopping_assistant_events events where events.shopping_assistant_conversation_id = shopping_assistant_conversations.id and events.type = '$type') as $type";

        if ($filters['outcome'] === 'ordered') {
            [$orderedSql, $orderedBindings] = $report->attributionSql(
                $report->conversationsQuery($filters, $filters['from']->startOfDay(), $filters['to']->endOfDay()),
                $amountColumn,
                'select matches.conversation_id from matches'
            );
            $query->whereRaw("shopping_assistant_conversations.id in ($orderedSql)", $orderedBindings);
        } elseif ($filters['outcome']) {
            $query->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('shopping_assistant_events')
                ->whereColumn('shopping_assistant_events.shopping_assistant_conversation_id', 'shopping_assistant_conversations.id')
                ->where('shopping_assistant_events.type', $filters['outcome']));
        }

        $conversations = $query
            ->select([
                'shopping_assistant_conversations.*',
                'customers.name as customer_name',
                'customers.reference as customer_reference',
                'customers.slug as customer_slug',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                'websites.domain as website_domain',
            ])
            ->selectRaw("(select left(messages.body, 160) from shopping_assistant_messages messages where messages.shopping_assistant_conversation_id = shopping_assistant_conversations.id and messages.role = 'customer' order by messages.id limit 1) as first_question")
            ->selectRaw($eventCount('shown'))
            ->selectRaw($eventCount('clicked'))
            ->selectRaw($eventCount('added'))
            ->defaultSort('-created_at')
            ->allowedSorts([
                AllowedSort::field('created_at', 'shopping_assistant_conversations.created_at'),
                AllowedSort::field('messages_count', 'shopping_assistant_conversations.messages_count'),
                AllowedSort::field('cost', 'shopping_assistant_conversations.cost'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();

        $outcomes = collect();
        $ids      = $conversations->getCollection()->pluck('id')->all();
        if ($ids) {
            $outcomes = collect($report->selectWithAttribution(
                DB::table('shopping_assistant_conversations')
                    ->whereIn('shopping_assistant_conversations.id', $ids)
                    ->select(['shopping_assistant_conversations.id', 'shopping_assistant_conversations.customer_id']),
                $amountColumn,
                'select convs.id, exists (select 1 from matches where matches.conversation_id = convs.id) as ordered, (select coalesce(sum(lines.amount), 0) from lines where lines.conversation_id = convs.id) as revenue from convs'
            ))->keyBy('id');
        }

        $conversations->getCollection()->each(function (ShoppingAssistantConversation $conversation) use ($outcomes, $usdRate) {
            $conversation->setAttribute('ordered', (bool) ($outcomes[$conversation->id]->ordered ?? false));
            $conversation->setAttribute('revenue', (float) ($outcomes[$conversation->id]->revenue ?? 0));
            $conversation->setAttribute('cost_in_page_currency', (float) $conversation->cost * ($usdRate ?? 1));
        });

        return $conversations;
    }

    public function tableStructure(bool $websiteLocked, string $prefix = ShoppingAssistantPerformanceTabsEnum::CONVERSATIONS->value): Closure
    {
        return function (InertiaTable $table) use ($websiteLocked, $prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('conversation'), __('conversations')])
                ->column(key: 'created_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, searchable: true);

            if (!$websiteLocked) {
                $table->column(key: 'website', label: __('Website'), canBeHidden: false);
            }

            $table
                ->column(key: 'entry_point', label: __('Opened from'), canBeHidden: false)
                ->column(key: 'first_question', label: __('First question'), canBeHidden: false)
                ->column(key: 'messages_count', label: __('Messages'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'shown', label: __('Shown'), canBeHidden: false, align: 'right')
                ->column(key: 'clicked', label: __('Clicked'), canBeHidden: false, align: 'right')
                ->column(key: 'added', label: __('Added'), canBeHidden: false, align: 'right')
                ->column(key: 'ordered', label: __('Ordered'), canBeHidden: false)
                ->column(key: 'cost', label: __('AI cost'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'status_label', label: __('Status'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }

    private function tabProp(ShoppingAssistantPerformanceTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title         = $this->inShopMenu ? __('AI Salesman') : __('AI Assistant');
        $websites      = $this->scopeWebsites($this->website);
        $filters       = $this->filters($request, $websites, (bool) $this->website);
        $report        = GetShoppingAssistantReport::make();
        [$currencyCode, $inShopCurrency] = $this->currency($this->website);
        $usdRate       = $report->usdRate($currencyCode);
        $amountColumn  = $inShopCurrency ? 'net_amount' : 'grp_net_amount';
        $routeParameters = $request->route()->originalParameters();
        $routePrefix   = match (true) {
            $this->inShopMenu       => 'grp.org.shops.show.ai_salesman.',
            (bool) $this->website   => 'grp.org.shops.show.web.analytics.shopping_assistant.',
            default                 => 'grp.websites.shopping_assistant.',
        };

        return Inertia::render(
            'Org/Web/ShoppingAssistantPerformance',
            [
                'title'            => $title,
                'breadcrumbs'      => $this->getBreadcrumbs($routeParameters),
                'pageHead'         => array_filter([
                    'title'         => $title,
                    'icon'          => ['icon' => ['fal', 'fa-robot'], 'title' => $title],
                    'model'         => $this->website?->name,
                    'subNavigation' => $this->website && !$this->inShopMenu ? $this->getWebsiteAnalyticsNavigation($this->website) : null,
                ]),
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => ShoppingAssistantPerformanceTabsEnum::navigation(),
                ],
                'filters'          => [
                    'from'           => $filters['from']->toDateString(),
                    'to'             => $filters['to']->toDateString(),
                    'previous_from'  => $filters['previous_from']->toDateString(),
                    'previous_to'    => $filters['previous_to']->toDateString(),
                    'website'        => $filters['website'],
                    'language'       => $filters['language'],
                    'entry_point'    => $filters['entry_point'],
                    'shop_type'      => $filters['shop_type'],
                    'country'        => $filters['country'],
                    'outcome'        => $filters['outcome'],
                    'website_locked' => $filters['website_locked'],
                    'options'        => $filters['options'],
                ],
                'currency'         => $currencyCode,
                'cost_currency'    => $usdRate === null ? 'USD' : $currencyCode,
                'attribution_days' => $report->attributionDays(),
                'budgets'          => $websites
                    ->filter(fn (Website $website) => data_get($website->settings, 'shopping_assistant.enabled'))
                    ->map(fn (Website $website) => ['website' => $website->domain, ...GetShoppingAssistantBudget::run($website)])
                    ->values()->all(),
                'routes'           => [
                    'page'         => ['name' => $routePrefix.'show', 'parameters' => $routeParameters],
                    'conversation' => ['name' => $routePrefix.'conversations.show', 'parameters' => $routeParameters],
                ],
                ShoppingAssistantPerformanceTabsEnum::OVERVIEW->value => $this->tabProp(
                    ShoppingAssistantPerformanceTabsEnum::OVERVIEW,
                    fn () => $report->handle($filters, $filters['from'], $filters['to'], $currencyCode, $inShopCurrency)
                ),
                ShoppingAssistantPerformanceTabsEnum::CONVERSATIONS->value => $this->tabProp(
                    ShoppingAssistantPerformanceTabsEnum::CONVERSATIONS,
                    fn () => ShoppingAssistantConversationsResource::collection($this->conversationsTable($filters, $amountColumn, $usdRate))
                ),
            ]
        )->table($this->tableStructure((bool) $this->website));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $label = __('AI Assistant');

        if ($this->inShopMenu) {
            return array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [[
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.org.shops.show.ai_salesman.show', 'parameters' => $routeParameters],
                        'label' => __('AI Salesman'),
                    ],
                ]]
            );
        }

        if ($this->website) {
            return array_merge(
                ShowWebsite::make()->getBreadcrumbs($this->website, 'grp.org.shops.show.web.websites.show', $routeParameters),
                [[
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.org.shops.show.web.analytics.shopping_assistant.show', 'parameters' => $routeParameters],
                        'label' => $label,
                    ],
                ]]
            );
        }

        return [
            ...WebsitesDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.websites.shopping_assistant.show', 'parameters' => []],
                    'label' => $label,
                ],
            ],
        ];
    }
}
