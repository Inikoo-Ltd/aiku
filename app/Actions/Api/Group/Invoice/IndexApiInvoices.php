<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Invoice;

use App\Actions\Api\Group\Resources\InvoicesApiResource;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class IndexApiInvoices extends OrgAction
{
    public function handle(Shop|Customer $parent, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Invoice::class);

        if ($parent instanceof Shop) {
            $query->where('invoices.shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('invoices.customer_id', $parent->id);
        }

        $query->leftJoin('customers', 'invoices.customer_id', 'customers.id');
        $query->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');
        $query->leftJoin('invoice_stats', 'invoice_stats.invoice_id', 'invoices.id');

        if (Arr::get($modelData, 'reference')) {
            $this->getReferenceSearch($query, Arr::get($modelData, 'reference'));
        } elseif (Arr::get($modelData, 'customerId')) {
            $this->getCustomerIdSearch($query, Arr::get($modelData, 'customerId'));
        }

        return $query->defaultSort('-date')
        ->select([
                'invoices.id',
                'invoices.slug',
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.in_process',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'customers.name as customer_name',
        ])
            ->allowedSorts(['reference', 'net_amount', 'pay_status', 'date'])
            ->withBetweenDates(['created_at'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }

    public function inCustomer(Shop $shop, Customer $customer, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($customer, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return InvoicesApiResource::collection($invoices);
    }

    public function getReferenceSearch($query, string $ref): QueryBuilder
    {
        return $query->where(function ($query) use ($ref) {
            $query->where('invoices.reference', $ref);
        });
    }

    public function getCustomerIdSearch($query, string $id): QueryBuilder
    {
        return $query->where(function ($query) use ($id) {
            $query->where('invoices.customer_id', $id);
        });
    }

    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string'],
            'customerId' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'reference' => $request->query('reference', null),
                'customerId' => $request->query('customerId', null),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
