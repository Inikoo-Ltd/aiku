<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ebay_users', function (Blueprint $table) {
            $table->string('fulfillment_policy_id')->nullable();
            $table->string('payment_policy_id')->nullable();
            $table->string('return_policy_id')->nullable();
            $table->string('location_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ebay_users', function (Blueprint $table) {
            $table->dropColumn('fulfillment_policy_id');
            $table->dropColumn('payment_policy_id');
            $table->dropColumn('return_policy_id');
            $table->dropColumn('location_key');
        });
    }
};
