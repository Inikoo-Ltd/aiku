<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\CRM\Customer\CompanyIdLabels;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Business customers invoiced in the last 12 months in an e-invoice country who cannot be reached on Peppol yet:
 * no Peppol ID and no validated Slovak VAT number to take the DIČ from. Same rule as IsInvoiceInEInvoiceScope and
 * BuildUblInvoice::buyerParticipantId, on each customer's latest invoice.
 * INI-592. Spanish customers are listed instead while neither their NIF nor their NIF-IVA is a valid NIF, so it is
 * collected before Spanish e-invoicing starts (the ES organisation, not e-invoicing yet, gets this page for that).
 */
class IndexCustomersMissingPeppolId extends OrgAction
{
    use WithAccountingAuthorisation;

    /**
     * @return array<int, string>
     */
    public static function countries(Organisation $organisation): array
    {
        return Arr::get($organisation->settings, 'e_invoicing.countries') ?: array_filter([$organisation->country?->code]);
    }

    public static function appliesTo(Organisation $organisation): bool
    {
        return Arr::get($organisation->settings, 'e_invoicing.countries') || $organisation->country?->code === 'ES';
    }

    public static function asksForNif(Organisation $organisation): bool
    {
        return self::countries($organisation) === ['ES'];
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $search = AllowedFilter::callback('global', function (Builder $query, mixed $value) {
            $value = trim((string)$value);
            $query->where(fn (Builder $query) => $query
                ->whereWith('customers.reference', $value)
                ->orWhereWith('customers.name', $value)
                ->orWhereWith('customers.company_name', $value));
        });

        $latestInvoices = DB::table('invoices')
            ->join('addresses', 'addresses.id', '=', 'invoices.address_id')
            ->where('invoices.organisation_id', $organisation->id)
            ->whereNull('invoices.deleted_at')
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', now()->subYear())
            ->whereIn('addresses.country_code', self::countries($organisation))
            ->selectRaw('distinct on (invoices.customer_id) invoices.customer_id, addresses.country_code, invoices.date, invoices.tax_number, invoices.tax_number_valid, invoices.identity_document_number')
            ->orderBy('invoices.customer_id')
            ->orderByDesc('invoices.date')
            ->orderByDesc('invoices.id');

        $query = Customer::query()
            ->joinSub($latestInvoices, 'latest_invoice', 'latest_invoice.customer_id', '=', 'customers.id')
            ->join('shops', 'shops.id', '=', 'customers.shop_id')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('latest_invoice.country_code', '<>', 'ES')
                    ->where(fn (Builder $query) => $query->whereNull('customers.peppol_id')->orWhereRaw("customers.peppol_id COLLATE \"C\" !~ '^[0-9]{4}:[A-Za-z0-9]{1,50}$'"))
                    ->whereRaw("not (coalesce(latest_invoice.tax_number_valid, false) and regexp_replace(upper(coalesce(latest_invoice.tax_number, '') COLLATE \"C\"), '[^A-Z0-9]', '', 'g') ~ '^SK[0-9]{10}$')"))
                ->orWhere(fn (Builder $query) => $query
                    ->where('latest_invoice.country_code', 'ES')
                    ->whereRaw('not '.CompanyIdLabels::spanishNifSql('customers.identity_document_number'))
                    ->whereNotExists(fn ($taxNumber) => $taxNumber->selectRaw('1')->from('tax_numbers')
                        ->where('tax_numbers.owner_type', 'Customer')
                        ->whereColumn('tax_numbers.owner_id', 'customers.id')
                        ->whereNull('tax_numbers.deleted_at')
                        ->where('tax_numbers.country_code', 'ES')
                        ->whereRaw(CompanyIdLabels::spanishNifSql('tax_numbers.number')))))
            ->where(fn (Builder $query) => $query
                ->whereRaw("length(regexp_replace(coalesce(latest_invoice.tax_number, '') COLLATE \"C\", '[^0-9]', '', 'g')) >= 8")
                ->orWhereRaw("length(regexp_replace(coalesce(latest_invoice.identity_document_number, '') COLLATE \"C\", '[^0-9]', '', 'g')) >= 8")
                ->orWhereRaw("trim(coalesce(customers.company_name, '')) <> ''"))
            ->select([
                'customers.id',
                'customers.slug',
                'customers.reference',
                'customers.name',
                'customers.company_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'latest_invoice.date as last_invoiced_at',
                'latest_invoice.tax_number',
                'latest_invoice.identity_document_number',
            ])
            ->selectSub(
                DB::table('invoices')->whereColumn('invoices.customer_id', 'customers.id')->whereNull('invoices.deleted_at')->where('invoices.in_process', false)->where('invoices.date', '>=', now()->subYear())->selectRaw('count(*)'),
                'invoices_last_year'
            );

        return QueryBuilder::for($query)
            ->defaultSort('-invoices_last_year')
            ->allowedSorts(['reference', 'name', 'last_invoiced_at', 'invoices_last_year'])
            ->allowedFilters([$search])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('customer'), __('customers')])
                ->withEmptyState(['title' => self::asksForNif($this->organisation) ? __('Every business customer has a valid NIF') : __('Every business customer can receive e-invoices')])
                ->column(key: 'reference', label: __('Customer'), sortable: true)
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'shop_code', label: __('Shop'))
                ->column(key: 'tax_number', label: __('Tax number'))
                ->column(key: 'identity_document_number', label: __('Company number'))
                ->column(key: 'last_invoiced_at', label: __('Last invoice'), sortable: true)
                ->column(key: 'invoices_last_year', label: __('Invoices (12 months)'), sortable: true, align: 'right');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $title = self::title($this->organisation);

        return Inertia::render(
            'Org/Accounting/CustomersMissingPeppolId',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => ['fal', 'fa-address-card'],
                    'title' => $title,
                ],
                'countries'   => self::countries($this->organisation),
                'asksForNif'  => self::asksForNif($this->organisation),
                'data'        => $customers->through(fn (Customer $customer) => [
                    'id'                       => $customer->id,
                    'reference'                => $customer->reference,
                    'name'                     => $customer->company_name ?: $customer->name,
                    'shop_code'                => $customer->shop_code,
                    'tax_number'               => $customer->tax_number,
                    'identity_document_number' => $customer->identity_document_number,
                    'last_invoiced_at'         => $customer->last_invoiced_at,
                    'invoices_last_year'       => (int)$customer->invoices_last_year,
                    'edit_route'               => [
                        'name'       => 'grp.org.shops.show.crm.customers.edit',
                        'parameters' => [$this->organisation->slug, $customer->shop_slug, $customer->slug],
                    ],
                ]),
            ]
        )->table($this->tableStructure());
    }

    public static function title(Organisation $organisation): string
    {
        return self::asksForNif($organisation) ? __('Customers missing a NIF') : __('Customers missing a Peppol ID');
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => $routeName, 'parameters' => $routeParameters],
                        'label' => self::title($this->organisation),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
