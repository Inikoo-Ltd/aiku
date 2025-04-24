<?php

use App\Stubs\Migrations\HasOrderingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasOrderingStats;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_has_platforms', function (Blueprint $table) {
            $table->unsignedInteger('number_customer_clients')->default(0);
            $table->unsignedInteger('number_portfolios')->default(0);

            $this->ordersStatsFields($table);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_has_platforms', function (Blueprint $table) {
            //
        });
    }
};
