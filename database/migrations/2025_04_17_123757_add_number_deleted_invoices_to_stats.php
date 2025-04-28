<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Apr 2025 20:38:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });

        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });

        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });

        Schema::table('invoice_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });

        Schema::table('customer_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });

        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_deleted_invoices')->default(0);
        });


    }


    public function down(): void
    {
        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
        Schema::table('invoice_category_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->dropColumn('number_deleted_invoices');
        });
    }
};
