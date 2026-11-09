<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 13:15
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
            $table->unsignedInteger('agent_payment_id')->nullable()->index();
            $table->foreign('agent_payment_id')->references('id')->on('agent_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_request_items', function (Blueprint $table) {
            $table->dropForeign(['agent_payment_id']);
            $table->dropColumn('agent_payment_id');
        });
    }
};
