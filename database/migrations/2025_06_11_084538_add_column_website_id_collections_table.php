<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedSmallInteger('website_id')->nullable()->index();
            $table->foreign('website_id')->references('id')->on('websites');
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropForeign(['website_id']);
            $table->dropColumn('website_id');
        });
    }
};
