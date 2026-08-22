<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 12:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Dashboard digest of the payment methods table: successful payments of the last N days by
 * method, with the share of sales, in the parent's currency.
 */
class GetPaymentMethodsSummary
{
    use AsObject;

    public function handle(Organisation|Shop $parent, int $days = 30, int $limit = 6): array
    {
        $query = Payment::query()
            ->join('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->where('payments.type', PaymentTypeEnum::PAYMENT)
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->where('payments.date', '>=', now()->subDays($days))
            ->whereNotNull('payments.method');

        if ($parent instanceof Shop) {
            $query->where('payments.shop_id', $parent->id)
                ->select('payments.method', 'payment_accounts.type as payment_account_type', DB::raw('count(*) as number_payments'), DB::raw('sum(payments.amount) as total_sales'));
        } else {
            $query->where('payments.organisation_id', $parent->id)
                ->select('payments.method', 'payment_accounts.type as payment_account_type', DB::raw('count(*) as number_payments'), DB::raw('sum(payments.org_amount) as total_sales'));
        }

        $rows = $query->groupBy('payments.method', 'payment_accounts.type')
            ->orderByDesc('total_sales')
            ->get();

        $total = (float) $rows->sum('total_sales');

        return [
            'currency_code' => $parent->currency->code,
            'days'          => $days,
            'total_sales'   => $total,
            'methods'       => $rows->take($limit)->map(fn ($row) => [
                'method'               => $row->method,
                'method_label'         => Payment::methodLabel($row->method),
                'payment_account_type' => $row->payment_account_type,
                'number_payments'      => (int) $row->number_payments,
                'total_sales'          => (float) $row->total_sales,
                'share'                => $total > 0 ? round($row->total_sales * 100 / $total, 1) : 0,
            ])->values()->all(),
            'others'        => $rows->count() - min($rows->count(), $limit),
        ];
    }
}
