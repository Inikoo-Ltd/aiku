<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->unsignedBigInteger('invoices')->default(0);
            $table->unsignedBigInteger('refunds')->default(0);
            $table->unsignedBigInteger('orders')->default(0);
            $table->unsignedBigInteger('registrations')->default(0);

            $table->decimal('baskets_created', 16)->default(0.00);
            $table->decimal('baskets_created_grp_currency', 16)->default(0.00);
            $table->decimal('baskets_created_org_currency', 16)->default(0.00);
            $table->decimal('sales', 16)->default(0.00);
            $table->decimal('sales_grp_currency', 16)->default(0.00);
            $table->decimal('sales_org_currency', 16)->default(0.00);
            $table->decimal('revenue', 16)->default(0.00);
            $table->decimal('revenue_grp_currency', 16)->default(0.00);
            $table->decimal('revenue_org_currency', 16)->default(0.00);
            $table->decimal('lost_revenue', 16)->default(0.00);
            $table->decimal('lost_revenue_grp_currency', 16)->default(0.00);
            $table->decimal('lost_revenue_org_currency', 16)->default(0.00);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sales_metrics');
    }
};
