<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedBigInteger('new_in_page_id')->nullable()->comment('System page listing the families and products first available in the last 30 days');
            $table->foreign('new_in_page_id')->references('id')->on('webpages');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['new_in_page_id']);
            $table->dropColumn('new_in_page_id');
        });
    }
};
