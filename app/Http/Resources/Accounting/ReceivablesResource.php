<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Accounting\Receivables\UI\IndexReceivables;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A row of a Receivables list: an unpaid invoice, a debtor or a customer balance, with the fields its query selected.
 */
class ReceivablesResource extends JsonResource
{
    public function toArray($request): array
    {
        $row    = (object) $this->resource->getAttributes();
        $money  = fn (string $field) => isset($row->{$field}) ? round((float) $row->{$field}, 2) : null;
        $date   = fn (string $field) => isset($row->{$field}) ? Carbon::parse($row->{$field})->toIso8601String() : null;
        $number = fn (string $field) => isset($row->{$field}) ? (int) $row->{$field} : null;

        return [
            'id'                => $row->id,
            'reference'         => $row->reference ?? null,
            'invoice_route'     => isset($row->invoice_slug) ? ['name' => 'grp.org.accounting.invoices.show', 'parameters' => [$row->organisation_slug, $row->invoice_slug]] : null,
            'customer_name'     => $row->customer_name,
            'customer_reference' => $row->customer_reference,
            'customer_route'    => IndexReceivables::customerRoute($row),
            'organisation_code' => $row->organisation_code,
            'shop_code'         => $row->shop_code,
            'currency_code'     => $row->currency_code,
            'date'              => $date('date'),
            'oldest'            => $date('oldest'),
            'days_overdue'      => $number('days_overdue'),
            'invoices'          => $number('invoices'),
            'total_amount'      => $money('total_amount'),
            'native'            => $money('native'),
            'amount'            => $money('amount'),
            'balance'           => $money('balance'),
            'order_reference'   => $row->order_reference ?? null,
            'order_route'       => isset($row->order_slug) ? ['name' => 'grp.org.shops.show.ordering.orders.show', 'parameters' => [$row->organisation_slug, $row->shop_slug, $row->order_slug]] : null,
            'dispatched_at'     => $date('dispatched_at'),
            'days_outstanding'  => $number('days_outstanding'),
            'couriers'          => $row->couriers ?? null,
            'tracking'          => $row->tracking ?? null,
            'is_cash_on_delivery' => (bool) ($row->is_cash_on_delivery ?? false),
            'is_pastpay'        => (bool) ($row->is_pastpay ?? false),
            'company'           => isset($row->as_organisation_id),
        ];
    }
}
