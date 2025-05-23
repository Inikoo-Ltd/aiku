<?php

use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->string('state')->default(CustomerSalesChannelStateEnum::CREATED->value);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropColumn(['state']);
        });
    }
};
