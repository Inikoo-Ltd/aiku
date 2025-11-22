<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->boolean('is_down')->nullable();
            $table->dateTimeTz('checked_as_down_at')->nullable();
            $table->integer('checked_as_down_days')->nullable();
            $table->integer('number_downside')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropColumn([
                'is_down',
                'checked_as_down_at',
                'checked_as_down_days',
                'number_downside'
            ]);
        });
    }
};