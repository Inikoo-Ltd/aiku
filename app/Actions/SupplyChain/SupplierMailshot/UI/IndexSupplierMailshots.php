<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Emails staff wrote to the suppliers of some countries, with how many were sent, read and clicked.
 */
class IndexSupplierMailshots extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithSupplierMailshotsSubNavigation;

    public function handle(Group $group): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('mailshots.subject', $value);
        });

        return QueryBuilder::for(Mailshot::class)
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', 'mailshots.id')
            ->leftJoin('shops', 'shops.id', 'mailshots.shop_id')
            ->where('mailshots.group_id', $group->id)
            ->where('mailshots.type', MailshotTypeEnum::SUPPLIER)
            ->defaultSort('-id')
            ->select([
                'mailshots.id',
                'mailshots.slug',
                'mailshots.subject',
                'mailshots.state',
                'mailshots.sent_at',
                'mailshots.created_at',
                'mailshots.recipients_recipe',
                'shops.code as shop_code',
                'mailshot_stats.number_dispatched_emails',
                'mailshot_stats.number_dispatched_emails_state_opened',
                'mailshot_stats.number_dispatched_emails_state_clicked',
            ])
            ->allowedSorts(['id', 'subject', 'sent_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('email'), __('emails')])
                ->withEmptyState(['title' => __('No emails to suppliers yet')])
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'countries', label: __('Countries'), canBeHidden: false)
                ->column(key: 'shop_code', label: __('Sent as'), canBeHidden: false)
                ->column(key: 'state', label: __('Status'), canBeHidden: false)
                ->column(key: 'sent_at', label: __('Sent'), canBeHidden: false, sortable: true)
                ->column(key: 'number_dispatched_emails', label: __('Emails'), canBeHidden: false, align: 'right')
                ->column(key: 'number_dispatched_emails_state_opened', label: __('Read'), canBeHidden: false, align: 'right')
                ->column(key: 'number_dispatched_emails_state_clicked', label: __('Clicked'), canBeHidden: false, align: 'right')
                ->defaultSort('-id');
        };
    }

    /**
     * The countries of the group's active suppliers, with how many suppliers each, for choosing who an email goes to.
     *
     * @return list<array{value: int, label: string, count: int}>
     */
    public function countryOptions(Group $group): array
    {
        return DB::table('suppliers')
            ->join('addresses', 'addresses.id', 'suppliers.address_id')
            ->join('countries', 'countries.id', 'addresses.country_id')
            ->where('suppliers.group_id', $group->id)
            ->where('suppliers.status', true)
            ->whereNull('suppliers.deleted_at')
            ->groupBy('countries.id', 'countries.name')
            ->orderBy('countries.name')
            ->get(['countries.id', 'countries.name', DB::raw('count(*) as count')])
            ->map(fn ($country) => ['value' => $country->id, 'label' => $country->name, 'count' => (int) $country->count])
            ->all();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(LengthAwarePaginator $mailshots): Response
    {
        $countryNames = collect($this->countryOptions($this->group))->pluck('label', 'value');
        $shops        = Shop::where('group_id', $this->group->id)->where('state', ShopStateEnum::OPEN)->orderBy('code')->get(['id', 'code', 'name', 'settings']);
        $defaultShop  = $shops->first(fn (Shop $shop) => $shop->sendsNewsletterToSuppliers()) ?? $shops->firstWhere('code', 'UK') ?? $shops->first();

        return Inertia::render('SupplyChain/SupplierMailshots', [
            'title'       => __('Emails to suppliers'),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'pageHead'    => [
                'title'         => __('Emails to suppliers'),
                'icon'          => ['icon' => ['fal', 'fa-mail-bulk'], 'title' => __('Emails to suppliers')],
                'subNavigation' => $this->getSuppliersSubNavigation(),
            ],
            'can_send'    => $this->canEdit,
            'store_route' => ['name' => 'grp.models.supplier_mailshot.store', 'parameters' => []],
            'countries'   => $this->countryOptions($this->group),
            'shops'       => $shops->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->code.' · '.$shop->name])->values(),
            'default_shop_id' => $defaultShop?->id,
            'data'        => $mailshots->through(fn (Mailshot $mailshot) => [
                'slug'      => $mailshot->slug,
                'subject'   => $mailshot->subject,
                'countries' => collect(data_get($mailshot->recipients_recipe, 'countries', []))->map(fn ($id) => $countryNames[$id] ?? null)->filter()->implode(', '),
                'shop_code' => $mailshot->shop_code,
                'state'     => $mailshot->state->value,
                'state_label' => $mailshot->state->labels()[$mailshot->state->value] ?? $mailshot->state->value,
                'sent_at'   => $mailshot->sent_at,
                'number_dispatched_emails'               => (int) $mailshot->number_dispatched_emails,
                'number_dispatched_emails_state_opened'  => (int) $mailshot->number_dispatched_emails_state_opened,
                'number_dispatched_emails_state_clicked' => (int) $mailshot->number_dispatched_emails_state_clicked,
            ]),
        ])->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Emails to suppliers'),
                        'route' => ['name' => 'grp.supply-chain.supplier_mailshots.index'],
                    ],
                ],
            ]
        );
    }
}
