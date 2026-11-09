<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 18:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('deposit_request_items', function (Blueprint $table) {
            $table->unsignedInteger('stock_delivery_id')->nullable()->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_request_items', function (Blueprint $table) {
            $table->dropForeign(['stock_delivery_id']);
            $table->dropColumn('stock_delivery_id');
        });
    }
};
