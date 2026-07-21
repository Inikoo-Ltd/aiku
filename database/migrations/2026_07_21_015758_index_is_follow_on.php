<?php

/*
 * Author Louis Perez
 * Created on 21-07-2026-09h-59m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('is_follow_on');
        });
    }


    public function down(): void
    {
        if (Schema::hasIndex('transactions', 'transactions_is_follow_on_index')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['is_follow_on']);
            });
        }
    }
};
