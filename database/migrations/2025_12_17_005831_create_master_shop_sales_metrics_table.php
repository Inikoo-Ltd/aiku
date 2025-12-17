<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Wed, 17 Dec 2025 09:57:23 WITA
 * Location: Lembeng Beach, Bali, Indonesia
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_shop_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('master_shop_id');
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->unsignedBigInteger('invoices')->default(0);
            $table->unsignedBigInteger('refunds')->default(0);
            $table->unsignedBigInteger('orders')->default(0);
            $table->unsignedBigInteger('registrations')->default(0);

            $table->decimal('baskets_created_grp_currency', 16)->default(0.00);
            $table->decimal('sales_grp_currency', 16)->default(0.00);
            $table->decimal('revenue_grp_currency', 16)->default(0.00);
            $table->decimal('lost_revenue_grp_currency', 16)->default(0.00);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_shop_sales_metrics');
    }
};
