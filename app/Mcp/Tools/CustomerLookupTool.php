<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find customers in a shop by reference, name, company, email or phone and return their contact details, status, balance, order counts, lifetime sales and last order/invoice dates. Returns the customer slug needed by customer-notes-tool.')]
#[IsReadOnly]
class CustomerLookupTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::CRM_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $query = (string) $request->string('query');
        $like  = '%'.$query.'%';

        $customers = Customer::where('shop_id', $shop->id)
            ->with('stats:customer_id,number_orders,number_invoices,sales_all,last_order_submitted_at,last_invoiced_at,number_unpaid_invoices,unpaid_invoices_amount')
            ->where(function ($q) use ($query, $like) {
                $q->whereRaw('lower(reference) = ?', [strtolower($query)])
                    ->orWhere('name', 'ilike', $like)
                    ->orWhere('contact_name', 'ilike', $like)
                    ->orWhere('company_name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like);
            })
            ->orderByRaw('lower(reference) = ? desc', [strtolower($query)])
            ->orderBy('id')
            ->limit($request->integer('limit', 10))
            ->get();

        return Response::json([
            'shop'     => $shop->name,
            'currency' => $shop->currency->code,
            'results'  => $customers->map(fn (Customer $customer) => [
                'slug'                   => $customer->slug,
                'reference'              => $customer->reference,
                'name'                   => $customer->name,
                'contact_name'           => $customer->contact_name,
                'company_name'           => $customer->company_name,
                'email'                  => $customer->email,
                'phone'                  => $customer->phone,
                'location'               => $customer->location,
                'state'                  => $customer->state?->value,
                'status'                 => $customer->status?->value,
                'balance'                => $customer->balance,
                'registered_at'          => $customer->registered_at,
                'number_orders'          => $customer->stats?->number_orders ?? 0,
                'number_invoices'        => $customer->stats?->number_invoices ?? 0,
                'sales_all'              => $customer->stats?->sales_all ?? 0,
                'last_order_at'          => $customer->stats?->last_order_submitted_at,
                'last_invoiced_at'       => $customer->stats?->last_invoiced_at,
                'number_unpaid_invoices' => $customer->stats?->number_unpaid_invoices ?? 0,
                'unpaid_invoices_amount' => $customer->stats?->unpaid_invoices_amount ?? 0,
            ])->values(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'query' => $schema->string()->description('Customer reference, or text matched against name, contact, company, email and phone')->required(),
            'limit' => $schema->integer()->description('Maximum customers to return, default 10')->min(1)->max(50),
        ];
    }
}
