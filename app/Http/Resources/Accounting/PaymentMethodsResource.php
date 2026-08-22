<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\Payment;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $method
 * @property int $number_payments
 * @property float $total_sales
 * @property int $number_success
 * @property float $success_rate
 * @property string $currency_code
 * @property string $organisation_slug
 */
class PaymentMethodsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'method'           => $this->method,
            'method_label'     => Payment::methodLabel($this->method),
            'payment_account_type' => $this->payment_account_type,
            'sales_share'      => (float) $this->currency_total_sales > 0
                ? number_format((float) $this->total_sales * 100 / (float) $this->currency_total_sales, 2, '.', '')
                : '0.00',
            'number_payments'  => (int) $this->number_payments,
            'total_sales'      => number_format((float) $this->total_sales, 2, '.', ''),
            'number_success'   => (int) $this->number_success,
            'success_rate'     => number_format((float) $this->success_rate, 2, '.', ''),
            'currency_code'    => $this->currency_code,
            'href'             => $this->shop_slug
                ? route('grp.org.shops.show.dashboard.payments.accounting.payments.index', [
                    'organisation' => $this->organisation_slug,
                    'shop'         => $this->shop_slug,
                    'filter'       => ['method' => $this->method],
                ])
                : route('grp.org.accounting.payments.index', [
                    'organisation' => $this->organisation_slug,
                    'filter'       => ['method' => $this->method],
                ]),
        ];
    }
}
