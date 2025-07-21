<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('traffic_source_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('traffic_source_id')->index();
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources');

            $table->unsignedInteger('number_customers')->default(0);
            $table->unsignedInteger('number_customer_purchases')->default(0);
            $table->decimal('total_customer_revenue', 16, 2)->default(0);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('traffic_source_stats');
    }
};
