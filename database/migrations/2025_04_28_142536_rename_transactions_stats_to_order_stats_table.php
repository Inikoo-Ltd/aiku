<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2025 22:25:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('order_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('customer_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('master_shop_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('master_product_category_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('master_asset_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('asset_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('product_category_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('collection_ordering_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('customer_client_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });

        Schema::table('platform_stats', function (Blueprint $table) {
            $this->renameFields($table);
        });
    }


    public function down(): void
    {
        Schema::table('order_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });
        Schema::table('customer_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('master_shop_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('master_product_category_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('master_asset_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('asset_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('product_category_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('collection_ordering_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('customer_client_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });

        Schema::table('platform_stats', function (Blueprint $table) {
            $this->undoRenameFields($table);
        });
    }

    public function renameFields(Blueprint $table): void
    {
        if ($table->getTable() == 'order_stats') {
            $table->renameColumn('number_transactions_at_submission', 'number_item_transactions_at_submission');
            $table->renameColumn('number_created_transactions_after_submission', 'number_created_item_transactions_after_submission');
            $table->renameColumn('number_updated_transactions_after_submission', 'number_updated_item_transactions_after_submission');
            $table->renameColumn('number_deleted_transactions_after_submission', 'number_deleted_item_transactions_after_submission');
        }

        $table->renameColumn('number_transactions_out_of_stock_in_basket', 'number_item_transactions_out_of_stock_in_basket');
        $table->renameColumn('number_transactions', 'number_item_transactions');
        $table->renameColumn('number_current_transactions', 'number_current_item_transactions');
        $table->renameColumn('number_transactions_state_creating', 'number_item_transactions_state_creating');
        $table->renameColumn('number_transactions_state_submitted', 'number_item_transactions_state_submitted');
        $table->renameColumn('number_transactions_state_in_warehouse', 'number_item_transactions_state_in_warehouse');
        $table->renameColumn('number_transactions_state_handling', 'number_item_transactions_state_handling');
        $table->renameColumn('number_transactions_state_packed', 'number_item_transactions_state_packed');
        $table->renameColumn('number_transactions_state_finalised', 'number_item_transactions_state_finalised');
        $table->renameColumn('number_transactions_state_dispatched', 'number_item_transactions_state_dispatched');
        $table->renameColumn('number_transactions_state_cancelled', 'number_item_transactions_state_cancelled');
        $table->renameColumn('number_transactions_status_creating', 'number_item_transactions_status_creating');
        $table->renameColumn('number_transactions_status_processing', 'number_item_transactions_status_processing');
        $table->renameColumn('number_transactions_status_settled', 'number_item_transactions_status_settled');
    }

    public function undoRenameFields(Blueprint $table): void
    {
        if ($table->getTable() == 'order_stats') {
            $table->renameColumn('number_item_transactions_at_submission', 'number_transactions_at_submission');
            $table->renameColumn('number_created_item_transactions_after_submission', 'number_created_transactions_after_submission');
            $table->renameColumn('number_updated_item_transactions_after_submission', 'number_updated_transactions_after_submission');
            $table->renameColumn('number_deleted_item_transactions_after_submission', 'number_deleted_transactions_after_submission');
        }

        $table->renameColumn('number_item_transactions_out_of_stock_in_basket', 'number_transactions_out_of_stock_in_basket');

        $table->renameColumn('number_item_transactions', 'number_transactions');
        $table->renameColumn('number_current_item_transactions', 'number_current_transactions');
        $table->renameColumn('number_item_transactions_state_creating', 'number_transactions_state_creating');
        $table->renameColumn('number_item_transactions_state_submitted', 'number_transactions_state_submitted');
        $table->renameColumn('number_item_transactions_state_in_warehouse', 'number_transactions_state_in_warehouse');
        $table->renameColumn('number_item_transactions_state_handling', 'number_transactions_state_handling');
        $table->renameColumn('number_item_transactions_state_packed', 'number_transactions_state_packed');
        $table->renameColumn('number_item_transactions_state_finalised', 'number_transactions_state_finalised');
        $table->renameColumn('number_item_transactions_state_dispatched', 'number_transactions_state_dispatched');
        $table->renameColumn('number_item_transactions_state_cancelled', 'number_transactions_state_cancelled');
        $table->renameColumn('number_item_transactions_status_creating', 'number_transactions_status_creating');
        $table->renameColumn('number_item_transactions_status_processing', 'number_transactions_status_processing');
        $table->renameColumn('number_item_transactions_status_settled', 'number_transactions_status_settled');
    }
};
