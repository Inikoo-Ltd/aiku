<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payables;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The open documents behind Payables, one list per kind, and Creditors: one row per supplier, agent or issuer of
 * service invoices in each organisation, with what it is owed, how many documents and the oldest of them.
 * Creditors holds only what is owed; the lists also show the rows that are not (an agent invoice already in the
 * agent's wallet, a money request awaiting approval, goods not invoiced yet), marked as such.
 */
class IndexPayables extends OrgAction
{
    use WithPayables;

    private string $section = 'supplier_invoices';

    public function handle(string $section): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('payables.creditor_name', $value)->orWhereWith('payables.reference', $value);
            });
        });

        $query = QueryBuilder::for(Currency::query()->fromSub($this->payables(), 'payables'));

        if ($section === 'creditors') {
            return $query
                ->where('payables.counted', true)
                ->selectRaw("payables.organisation_id || '|' || payables.creditor_key as id, min(payables.creditor_name) as creditor_name, min(payables.creditor_type) as creditor_type, min(payables.creditor_slug) as creditor_slug")
                ->selectRaw('min(payables.organisation_code) as organisation_code, min(payables.organisation_slug) as organisation_slug')
                ->selectRaw('count(*) as documents, sum(payables.amount) as amount, min(payables.date) as oldest_date, min(payables.due_date) as oldest_due_date')
                ->selectRaw("string_agg(distinct payables.currency_code, ', ' order by payables.currency_code) as currency_code")
                ->groupBy('payables.organisation_id', 'payables.creditor_key')
                ->defaultSort('-amount')
                ->allowedSorts(['amount', 'documents', 'oldest_date', 'creditor_name'])
                ->allowedFilters([$globalSearch])
                ->withPaginator(null, tableName: request()->route()->getName())
                ->withQueryString();
        }

        return $query
            ->select('payables.*')
            ->where('payables.kind', $section)
            ->defaultSort('date')
            ->allowedSorts(['date', 'amount', 'creditor_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->inSection($request);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->readGroup($request);

        return $this->inSection($request);
    }

    private function inSection(ActionRequest $request): LengthAwarePaginator
    {
        $this->section = str($request->route()->getName())->afterLast('.')->toString();
        abort_unless(array_key_exists($this->section, self::sections()), 404);

        return $this->handle($this->section);
    }

    public function htmlResponse(LengthAwarePaginator $payables, ActionRequest $request): Response
    {
        $title = self::sections()[$this->section][0];

        return Inertia::render(
            'Org/Accounting/Payables',
            [
                'breadcrumbs'   => $this->payablesBreadcrumbs($request->route()->originalParameters()),
                'title'         => $title,
                'pageHead'      => [
                    'model'         => __('Payables'),
                    'title'         => $title,
                    'icon'          => ['fal', 'fa-file-invoice'],
                    'subNavigation' => $this->subNavigation(),
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $this->organisationOptions(),
                'section'       => $this->section,
                'currency'      => $this->parent()->currency->code,
                'data'          => JsonResource::collection($payables),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('Nothing owed here'),
                    'description' => __('No open document of this kind.'),
                    'count'       => 0,
                ]);

            if ($this->section === 'creditors') {
                $table->column(key: 'creditor_name', label: __('Creditor'), sortable: true);
                $table->column(key: 'creditor_type', label: __('Kind'));
                if ($this->isGroup()) {
                    $table->column(key: 'organisation_code', label: __('Organisation'));
                }
                $table->column(key: 'documents', label: __('Documents'), sortable: true, align: 'right');
                $table->column(key: 'currency_code', label: __('Currency'));
                $table->column(key: 'oldest_date', label: __('Oldest document'), sortable: true);
                $table->column(key: 'oldest_due_date', label: __('Oldest due'));
                $table->column(key: 'amount', label: __('Owed'), sortable: true, align: 'right');

                return;
            }

            $isWallets = $this->section === 'agent_wallets';
            if (!$isWallets) {
                $table->column(key: 'reference', label: $this->section === 'money_requests' ? __('Request') : __('Invoice'), sortable: true);
            }
            $table->column(key: 'creditor_name', label: match ($this->section) {
                'supplier_invoices' => __('Supplier'),
                'service_invoices'  => __('Issued by'),
                'not_invoiced'      => __('Supplier or agent'),
                default             => __('Agent'),
            }, sortable: true);
            if ($this->isGroup()) {
                $table->column(key: 'organisation_code', label: __('Organisation'));
            }
            if (in_array($this->section, ['supplier_invoices', 'agent_invoices', 'not_invoiced'], true)) {
                $table->column(key: 'stock_delivery_reference', label: __('Delivery'));
            }
            if ($this->section === 'money_requests') {
                $table->column(key: 'approved_at', label: __('Approved'));
            }
            if ($this->section === 'agent_invoices') {
                $table->column(key: 'counted', label: __('Counted'));
            }
            $table->column(key: 'date', label: $isWallets ? __('Last ledger line') : __('Date'), sortable: true);
            if (!$isWallets && $this->section !== 'not_invoiced') {
                $table->column(key: 'due_date', label: __('Due'));
            }
            $table->column(key: 'total', label: match ($this->section) {
                'agent_wallets' => __('Spent by the agent'),
                'not_invoiced'  => __('Estimated'),
                default         => __('Total'),
            }, align: 'right');
            if ($this->section !== 'service_invoices') {
                $table->column(key: 'paid', label: $isWallets ? __('Received by the agent') : __('Paid'), align: 'right');
            }
            $table->column(key: 'owed', label: $this->section === 'not_invoiced' ? __('Not paid') : __('Owed'), align: 'right');
            $table->column(key: 'amount', label: __('In :currency', ['currency' => $this->parent()->currency->code]), sortable: true, align: 'right');
        };
    }
}
