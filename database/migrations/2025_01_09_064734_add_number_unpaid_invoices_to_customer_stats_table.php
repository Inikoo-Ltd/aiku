<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jan 2025 15:23:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_unpaid_invoices')->default(0);
            $table->decimal('unpaid_invoices_amount', 16)->default(0);
            $table->decimal('unpaid_invoices_amount_org_currency', 16)->default(0);
            $table->decimal('unpaid_invoices_amount_grp_currency', 16)->default(0);

        });

        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_unpaid_invoices')->default(0);
            $table->decimal('unpaid_invoices_amount', 16)->default(0);
            $table->decimal('unpaid_invoices_amount_org_currency', 16)->default(0);
            $table->decimal('unpaid_invoices_amount_grp_currency', 16)->default(0);
        });

        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_unpaid_invoices')->default(0);
            $table->decimal('unpaid_invoices_amount_org_currency', 16)->default(0);
            $table->decimal('unpaid_invoices_amount_grp_currency', 16)->default(0);
        });

        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_unpaid_invoices')->default(0);
            $table->decimal('unpaid_invoices_amount_grp_currency', 16)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn(['number_unpaid_invoices', 'unpaid_invoices_amount', 'unpaid_invoices_amount_org_currency', 'unpaid_invoices_amount_grp_currency']);
        });

        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->dropColumn(['number_unpaid_invoices', 'unpaid_invoices_amount', 'unpaid_invoices_amount_org_currency', 'unpaid_invoices_amount_grp_currency']);
        });

        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $table->dropColumn(['number_unpaid_invoices', 'unpaid_invoices_amount_org_currency', 'unpaid_invoices_amount_grp_currency']);
        });

        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->dropColumn(['number_unpaid_invoices', 'unpaid_invoices_amount_grp_currency']);
        });
    }
};
