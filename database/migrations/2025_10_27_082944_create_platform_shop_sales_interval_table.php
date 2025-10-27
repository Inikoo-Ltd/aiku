<?php

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::create('platform_shop_sales_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('platform_id')->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table = $this->unsignedIntegerDateIntervals($table, [
                'invoices',
                'new_channels',
                'new_customers',
                'new_portfolios',
                'new_customer_client'
            ]);
            $table = $this->decimalDateIntervals($table, [
                'sales',
                'sales_org_currency',
                'sales_grp_currency'
            ]);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_shop_sales_intervals');
    }
};
