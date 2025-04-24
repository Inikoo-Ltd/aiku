<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 13:02:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasOrderingStats
{
    use HasAmounts;

    public function orderingStatsFields(Blueprint $table): Blueprint
    {
        $table = $this->ordersStatsFields($table);
        $table = $this->transactionsStatsFields($table);
        $table = $this->invoicesStatsFields($table);
        $table = $this->invoiceTransactionsStatsFields($table);
        $table = $this->invoicedCustomersStatsFields($table);
        $table = $this->deliveryNotesStatsFields($table);
        $table = $this->deliveryNoteItemsStatsFields($table);

        if (in_array($table->getTable(), ['group_ordering_stats', 'organisation_ordering_stats', 'shop_ordering_stats'])) {
            $table = $this->purgeStatsFields($table);
        }

        return $table;
    }


    public function ordersStatsFields(Blueprint $table): Blueprint
    {
        $table->dateTimeTz('last_order_created_at')->nullable();
        $table->dateTimeTz('last_order_submitted_at')->nullable();
        $table->dateTimeTz('last_order_dispatched_at')->nullable();
        $table->unsignedInteger('number_orders')->default(0);

        foreach (OrderStateEnum::cases() as $case) {
            $table->unsignedInteger('number_orders_state_'.$case->snake())->default(0);
        }

        foreach (OrderStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_orders_status_'.$case->snake())->default(0);
        }

        foreach (OrderHandingTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_orders_handing_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function ordersStatsFieldsDown(Blueprint $table): Blueprint
    {
        $table->dropColumn('last_order_created_at');
        $table->dropColumn('last_order_submitted_at');
        $table->dropColumn('last_order_dispatched_at');
        $table->dropColumn('number_orders');

        foreach (OrderStateEnum::cases() as $case) {
            $table->dropColumn('number_orders_state_'.$case->snake());
        }

        foreach (OrderStatusEnum::cases() as $case) {
            $table->dropColumn('number_orders_status_'.$case->snake());
        }

        foreach (OrderHandingTypeEnum::cases() as $case) {
            $table->dropColumn('number_orders_handing_type_'.$case->snake());
        }

        return $table;
    }

    public function invoicesStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_invoices')->default(0);
        $table->unsignedInteger('number_invoices_type_invoice')->default(0);
        $table->unsignedInteger('number_invoices_type_refund')->default(0);
        $table->dateTimeTz('last_invoiced_at')->nullable();

        return $table;
    }


    public function invoicedCustomersStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_invoiced_customers')->default(0);


        return $table;
    }


    public function deliveryNotesStatsFields(Blueprint $table): Blueprint
    {
        $table->dateTimeTz('last_delivery_note_created_at')->nullable();
        $table->dateTimeTz('last_delivery_note_dispatched_at')->nullable();

        $table->dateTimeTz('last_delivery_note_type_order_created_at')->nullable();
        $table->dateTimeTz('last_delivery_note_type_order_dispatched_at')->nullable();

        $table->dateTimeTz('last_delivery_note_type_replacement_created_at')->nullable();
        $table->dateTimeTz('last_delivery_note_type_replacement_dispatched_at')->nullable();


        $table->unsignedInteger('number_delivery_notes')->default(0);

        foreach (DeliveryNoteTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_delivery_notes_type_'.$case->snake())->default(0);
        }

        foreach (DeliveryNoteStateEnum::cases() as $case) {
            $table->unsignedInteger('number_delivery_notes_state_'.$case->snake())->default(0);
        }

        foreach (DeliveryNoteStateEnum::cases() as $case) {
            if ($case->value != 'cancelled') {
                $table->unsignedInteger('number_delivery_notes_cancelled_at_state_'.$case->snake())->default(0);
            }
        }

        $table->unsignedInteger('number_delivery_notes_state_with_out_of_stock')->default(0);


        return $table;
    }

    public function purgeStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_purges')->default(0);

        foreach (PurgeStateEnum::cases() as $case) {
            $table->unsignedInteger('number_purges_state_'.$case->snake())->default(0);
        }

        foreach (PurgeTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_purges_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function transactionsStatsFields(Blueprint $table): Blueprint
    {
        $allowedCurrencies = $this->allowedCurrencies($table);

        $table->unsignedBigInteger('number_transactions_out_of_stock_in_basket')->default(0)->comment('transactions at the time up submission from basket');

        if ($allowedCurrencies['grp']) {
            $table->decimal('out_of_stock_in_basket_grp_net_amount', 16)->nullable();
        }

        if ($allowedCurrencies['org']) {
            $table->decimal('out_of_stock_in_basket_org_net_amount', 16)->nullable();
        }
        if ($allowedCurrencies['shop']) {
            $table->decimal('out_of_stock_in_basket_net_amount', 16)->default(0);
        }


        if ($table->getTable() == 'order_stats') {
            $table->unsignedBigInteger('number_transactions_at_submission')->default(0)->comment('transactions at the time up submission from basket');
            $table->unsignedBigInteger('number_created_transactions_after_submission')->default(0);
            $table->unsignedBigInteger('number_updated_transactions_after_submission')->default(0);
            $table->unsignedBigInteger('number_deleted_transactions_after_submission')->default(0);
        }

        $table->unsignedBigInteger('number_transactions')->default(0)->comment('transactions including cancelled');
        $table->unsignedBigInteger('number_current_transactions')->default(0)->comment('transactions excluding cancelled');

        foreach (TransactionStateEnum::cases() as $case) {
            $table->unsignedBigInteger('number_transactions_state_'.$case->snake())->default(0);
        }

        foreach (TransactionStatusEnum::cases() as $case) {
            $table->unsignedBigInteger('number_transactions_status_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function invoiceTransactionsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_invoice_transactions')->default(0)->comment('transactions including cancelled');
        $table->unsignedBigInteger('number_positive_invoice_transactions')->default(0)->comment('amount>0');
        $table->unsignedBigInteger('number_negative_invoice_transactions')->default(0)->comment('amount<0');
        $table->unsignedBigInteger('number_zero_invoice_transactions')->default(0)->comment('amount=0');

        $table->unsignedBigInteger('number_current_invoice_transactions')->default(0)->comment('transactions excluding cancelled');
        $table->unsignedBigInteger('number_positive_current_invoice_transactions')->default(0)->comment('transactions excluding cancelled, amount>0');
        $table->unsignedBigInteger('number_negative_current_invoice_transactions')->default(0)->comment('transactions excluding cancelled, amount<0');
        $table->unsignedBigInteger('number_zero_current_invoice_transactions')->default(0)->comment('transactions excluding cancelled, amount=0');


        return $table;
    }

    public function deliveryNoteItemsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_delivery_note_items')->default(0)->comment('transactions including cancelled');
        $table->unsignedBigInteger('number_uphold_delivery_note_items')->default(0)->comment('transactions excluding cancelled');

        foreach (DeliveryNoteItemStateEnum::cases() as $case) {
            $table->unsignedBigInteger('number_delivery_note_items_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function orderingOffersStatsFields(Blueprint $table): Blueprint
    {
        $allowedCurrencies = $this->allowedCurrencies($table);


        $table->unsignedSmallInteger('number_offer_campaigns')->default(0);
        $table->unsignedInteger('number_offers')->default(0);
        $table->unsignedInteger('number_offer_components')->default(0);

        $table->unsignedBigInteger('number_transactions_with_offers')->default(0);


        if ($allowedCurrencies['shop']) {
            $table->decimal('discounts_amount', 16)->default(0)->comment('from % offs');
            $table->decimal('giveaways_value_amount', 16)->default(0)->comment('Value of goods given for free');
            $table->decimal('cashback_amount', 16)->default(0);
        }
        if ($allowedCurrencies['org']) {
            $table->decimal('org_giveaways_value_amount', 16)->nullable();
            $table->decimal('org_cashback_amount', 16)->nullable();
            $table->decimal('org_discounts_amount', 16)->nullable();
        }
        if ($allowedCurrencies['grp']) {
            $table->decimal('grp_discounts_amount', 16)->nullable();
            $table->decimal('grp_giveaways_value_amount', 16)->nullable();
            $table->decimal('grp_cashback_amount', 16)->nullable();
        }


        return $table;
    }


}
