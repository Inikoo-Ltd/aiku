<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->unsignedBigInteger('external_shop_id')->index()->nullable();
            $table->foreign('external_shop_id')->references('id')->on('shops');
        });
    }

    public function down(): void
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->dropForeign(['external_shop_id']);
            $table->dropColumn('external_shop_id');
        });
    }
};
