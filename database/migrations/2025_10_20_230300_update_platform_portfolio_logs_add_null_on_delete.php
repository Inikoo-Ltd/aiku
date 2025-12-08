<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Oct 2025 23:03:40 Central Indonesia Time, Canggu, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing foreign key constraints first
        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['portfolio_id']);
        });

        // Alter columns to be nullable and recreate FKs with nullOnDelete
        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->change();
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedBigInteger('portfolio_id')->nullable()->change();

            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('portfolio_id')->references('id')->on('portfolios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revert to original constraints: non-nullable columns and FKs without nullOnDelete
        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['portfolio_id']);
        });

        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable(false)->change();
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->unsignedBigInteger('portfolio_id')->nullable(false)->change();

            $table->foreign('shop_id')->references('id')->on('shops');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('portfolio_id')->references('id')->on('portfolios');
        });
    }
};
