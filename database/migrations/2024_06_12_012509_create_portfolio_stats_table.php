<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_stats', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('portfolio_id')->index();
            $table->foreign('portfolio_id')->references('id')->on('portfolios');

            $table->decimal('amount')->default(0)->comment('Invoiced amount');
            $table->decimal('org_amount')->default(0)->comment('Invoiced amount');
            $table->decimal('grp_amount')->default(0)->comment('Invoiced amount');

            $table->unsignedSmallInteger('number_orders')->default(0);
            $table->unsignedSmallInteger('number_ordered_quantity')->default(0);
            $table->unsignedSmallInteger('number_customer_clients')->default(0);
            $table->dateTimeTz('last_ordered_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_stats');
    }
};
