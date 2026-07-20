<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 08 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\CheckoutAbandonment;

use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\CheckoutAbandonment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RunCheckoutAbandonmentScan
{
    use AsAction;

    public string $commandSignature = 'scan:checkout-abandonments';
    public string $jobQueue = 'ses';

    public const int THRESHOLD_HOURS = 24;

    public function handle(): void
    {
        $this->detectAbandoned();
        $this->markRecovered();
    }

    protected function detectAbandoned(): void
    {
        $rows = DB::select(
            '
            SELECT o.id AS order_id, o.group_id, o.organisation_id, o.shop_id, o.customer_id, o.total_amount, MAX(pv.created_at) AS checkout_visited_at
            FROM website_page_views pv
            JOIN website_visitors v ON v.id = pv.website_visitor_id
            JOIN web_users wu ON wu.id = v.web_user_id
            JOIN customers c ON c.id = wu.customer_id
            JOIN orders o ON o.id = c.current_order_in_basket_id
            WHERE pv.page_path = ?
              AND pv.created_at >= o.created_at
              AND pv.created_at < ?
              AND o.state = ?
              AND o.submitted_at IS NULL
              AND o.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND o.total_amount > 0
            GROUP BY o.id, o.group_id, o.organisation_id, o.shop_id, o.customer_id, o.total_amount
            ',
            [
                '/app/checkout',
                now()->subHours(self::THRESHOLD_HOURS),
                OrderStateEnum::CREATING->value,
            ]
        );

        if (empty($rows)) {
            return;
        }

        $now = now();
        $records = array_map(fn ($row) => [
            'group_id'            => $row->group_id,
            'organisation_id'     => $row->organisation_id,
            'shop_id'             => $row->shop_id,
            'order_id'            => $row->order_id,
            'customer_id'         => $row->customer_id,
            'checkout_visited_at' => $row->checkout_visited_at,
            'total_amount'        => $row->total_amount,
            'created_at'          => $now,
            'updated_at'          => $now,
        ], $rows);

        CheckoutAbandonment::upsert(
            $records,
            ['order_id'],
            ['checkout_visited_at', 'total_amount', 'updated_at']
        );
    }

    protected function markRecovered(): void
    {
        DB::statement(
            'UPDATE checkout_abandonments
             SET state = ?, recovered_at = now(), updated_at = now()
             FROM orders
             WHERE checkout_abandonments.order_id = orders.id
               AND checkout_abandonments.state = ?
               AND orders.state != ?',
            [
                CheckoutAbandonmentStateEnum::RECOVERED->value,
                CheckoutAbandonmentStateEnum::ABANDONED->value,
                OrderStateEnum::CREATING->value,
            ]
        );
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
