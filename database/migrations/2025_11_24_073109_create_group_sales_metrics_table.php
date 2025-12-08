<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('group_sales_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

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
        Schema::dropIfExists('group_sales_metrics');
    }
};
