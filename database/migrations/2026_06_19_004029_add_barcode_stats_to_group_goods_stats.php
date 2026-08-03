<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-09h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_barcodes')->default(0);
            $table->unsignedInteger('number_barcodes_status_available')->default(0);
            $table->unsignedInteger('number_barcodes_status_used')->default(0);
            $table->unsignedInteger('number_barcodes_status_reserved')->default(0);
        });
    }

    public function down()
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_barcodes',
                'number_barcodes_status_available',
                'number_barcodes_status_used',
                'number_barcodes_status_reserved'
            ]);
        });
    }
};
