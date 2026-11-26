<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancelled_by_user_id')->nullable()->index();
            $table->foreign('cancelled_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn('cancelled_by_user_id');
        });
    }
};
